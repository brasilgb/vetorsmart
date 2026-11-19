<?php

namespace App\Services\Portal;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\WebsiteStatus;
use App\Models\PortalListing;
use App\Models\Segment;
use App\Services\Billing\BillingEnforcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Search of the Regional Portal.
 *
 * Eligibility is checked on every query, never trusted from the index: active company that is not a test company,
 * active segment, published site whose current version is the indexed one, consent to the directory, and, while
 * billing is in force, a subscription that allows access (a company without a subscription keeps its access, as in
 * the panel).
 *
 * Ordering, transparent and without any commercial criterion (plan, credits, payments are never read):
 *
 * 1. service match: 2 when every searched word is in the published service names, 1 when only in the segment;
 * 2. region match: 2 when the city is the company's city, 1 when it is only mentioned in its service region;
 * 3. public information: 0 to 3 points (logo, description, at least one service);
 * 4. tie: a hash of the company and the day (CRC32), stable within a day so pages never shuffle, different every day
 *    so no company keeps the first place because of its name or registration order.
 */
class PortalSearch
{
    public const int PER_PAGE = 12;

    public const int MAX_PAGE = 50;

    public function __construct(private BillingEnforcement $enforcement) {}

    /**
     * @param  array{q?: string|null, cidade?: string|null, uf?: string|null, categoria?: string|null, nome?: string|null}  $filters
     * @return LengthAwarePaginator<int, PortalListing>
     */
    public function search(array $filters, int $page, string $seed, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $tokens = PortalText::tokens($filters['q'] ?? null);
        $city = PortalText::normalize($filters['cidade'] ?? null);
        $state = PortalText::state($filters['uf'] ?? null);
        $name = PortalText::normalize($filters['nome'] ?? null);
        $segment = $this->segment($filters['categoria'] ?? null);

        $query = $this->eligible()
            ->select('portal_listings.*', 'tenants.slug as tenant_slug', 'segments.slug as segment_slug', 'segments.name as segment_name');

        foreach ($tokens as $token) {
            $query->where(fn (Builder $match) => $match->where('portal_listings.services_search', 'like', "%{$token}%")->orWhere('portal_listings.segment_search', 'like', "%{$token}%"));
        }

        if ($city !== '') {
            $query->where(fn (Builder $match) => $match->where('portal_listings.city_search', $city)->orWhere('portal_listings.region_search', 'like', "%{$city}%"));
        }

        if ($state !== null) {
            $query->where('portal_listings.state', $state);
        }

        if ($name !== '') {
            $query->where('portal_listings.name_search', 'like', "%{$name}%");
        }

        if ($segment !== null) {
            $query->where('tenants.segment_id', $segment->id);
        }

        $serviceScore = $tokens === []
            ? DB::raw('0 as service_score')
            : DB::raw('(CASE WHEN '.implode(' AND ', array_fill(0, count($tokens), 'portal_listings.services_search LIKE ?')).' THEN 2 ELSE 1 END) as service_score');

        if ($tokens !== []) {
            $query->addBinding(array_map(fn (string $token): string => "%{$token}%", $tokens), 'select');
        }

        $query->addSelect($serviceScore);

        if ($city !== '') {
            $query->selectRaw('(CASE WHEN portal_listings.city_search = ? THEN 2 ELSE 1 END) as region_score', [$city]);
        } else {
            $query->selectRaw('0 as region_score');
        }

        $query->selectRaw('CRC32(CONCAT(portal_listings.tenant_id, ?)) as tie', [':'.$seed]);

        return $query
            ->orderByDesc('service_score')
            ->orderByDesc('region_score')
            ->orderByDesc('portal_listings.completeness')
            ->orderBy('tie')
            ->orderBy('portal_listings.id')
            ->paginate(min(max($perPage, 1), self::PER_PAGE), ['*'], 'pagina', min(max($page, 1), self::MAX_PAGE));
    }

    /**
     * Cities of the companies that appear in the portal, for the suggestion list of the city field.
     *
     * @return list<string>
     */
    public function cities(): array
    {
        return array_values($this->eligible()
            ->select('portal_listings.city', 'portal_listings.state')
            ->distinct()
            ->orderBy('portal_listings.city')
            ->limit(300)
            ->get()
            ->map(fn (PortalListing $listing): string => $listing->city.' - '.$listing->state)
            ->unique()
            ->all());
    }

    /**
     * Slugs of the segments that have at least one company in the portal right now, so the page never offers a
     * category that leads to an empty list (and only those category pages are indexable).
     *
     * @return list<string>
     */
    public function categoriesWithProviders(): array
    {
        return array_values(array_map('strval', $this->eligible()->distinct()->orderBy('segments.slug')->pluck('segments.slug')->all()));
    }

    /**
     * Active segments, for the category filter.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function categories(): array
    {
        return array_values(Segment::query()->where('active', true)->orderBy('name')->get(['slug', 'name'])
            ->map(fn (Segment $segment): array => ['slug' => $segment->slug, 'name' => $segment->name])->all());
    }

    /**
     * Display name of a municipality where at least one participating company is based, or null. Only the companies
     * based there count: a municipality mentioned only in a service region has no page of its own.
     */
    public function municipality(string $state, string $slug): ?string
    {
        $name = $this->eligible()
            ->where('portal_listings.state', $state)
            ->where('portal_listings.city_search', PortalText::cityFromSlug($slug))
            ->orderBy('portal_listings.id')
            ->value('portal_listings.city');

        return is_string($name) ? $name : null;
    }

    /**
     * Places with participating companies based there, with the categories present in each one. Bounded by the real
     * companies, so it never creates a page for an empty municipality.
     *
     * @return list<array{state: string, city: string, slug: string, categories: list<string>}>
     */
    public function places(): array
    {
        $rows = $this->eligible()
            ->select('portal_listings.state', 'portal_listings.city', 'portal_listings.city_search', 'segments.slug as segment_slug')
            ->orderBy('portal_listings.state')
            ->orderBy('portal_listings.city_search')
            ->orderBy('portal_listings.id')
            ->toBase()
            ->get();

        $places = [];

        foreach ($rows as $row) {
            $key = $row->state.'/'.$row->city_search;
            $places[$key] ??= ['state' => (string) $row->state, 'city' => (string) $row->city, 'slug' => str_replace(' ', '-', (string) $row->city_search), 'categories' => []];

            if (! in_array($row->segment_slug, $places[$key]['categories'], true)) {
                $places[$key]['categories'][] = (string) $row->segment_slug;
            }
        }

        return array_values($places);
    }

    /**
     * @return Builder<PortalListing>
     */
    private function eligible(): Builder
    {
        $query = PortalListing::query()
            ->join('tenants', 'tenants.id', '=', 'portal_listings.tenant_id')
            ->join('segments', 'segments.id', '=', 'tenants.segment_id')
            ->join('tenant_websites', 'tenant_websites.tenant_id', '=', 'portal_listings.tenant_id')
            ->where('tenants.status', TenantStatus::Active->value)
            ->where('tenants.is_homolog', false)
            ->where('segments.active', true)
            ->where('tenant_websites.status', WebsiteStatus::Published->value)
            ->where('tenant_websites.portal_listed', true)
            ->whereColumn('tenant_websites.published_version_id', 'portal_listings.website_version_id');

        if ($this->enforcement->active()) {
            $allowed = array_values(array_map(fn (SubscriptionStatus $status): string => $status->value, array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $status): bool => $status->allowsAccess())));

            $query->whereNotExists(fn ($subscription) => $subscription->selectRaw('1')
                ->from('subscriptions')
                ->whereColumn('subscriptions.tenant_id', 'tenants.id')
                ->whereNotIn('subscriptions.status', $allowed));
        }

        return $query;
    }

    private function segment(?string $slug): ?Segment
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return Segment::query()->where('slug', $slug)->where('active', true)->first();
    }
}
