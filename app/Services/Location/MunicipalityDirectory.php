<?php

namespace App\Services\Location;

use App\Models\Municipality;
use App\Services\Portal\PortalText;
use Illuminate\Support\Collection;

/**
 * Lookups in the official municipality registry (IBGE). Every place the visitor or the company chooses ends here, so the
 * portal, the sites and the requests always speak of the same municipality, whatever the spelling typed.
 */
class MunicipalityDirectory
{
    public function find(mixed $id): ?Municipality
    {
        $code = is_int($id) ? $id : (is_string($id) && ctype_digit($id) ? (int) $id : null);

        if ($code === null || $code < 1100000 || $code > 5399999) {
            return null;
        }

        return Municipality::query()->find($code);
    }

    /**
     * The municipality with this name in this state, or null. Accents, case and punctuation do not matter.
     */
    public function byName(?string $name, ?string $state): ?Municipality
    {
        $normalized = PortalText::normalize($name);
        $uf = PortalText::state($state);

        if ($normalized === '' || $uf === null) {
            return null;
        }

        return Municipality::query()->where('state', $uf)->where('name_search', $normalized)->first();
    }

    /**
     * A municipality typed as free text: "Salvador do Sul - RS", "Salvador do Sul, RS", "Salvador do Sul/RS", or the name
     * alone with the state given apart. A name without state is accepted only when it is unique in the country.
     */
    public function fromText(?string $text, ?string $state = null): ?Municipality
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(.+?)\s*(?:-|,|\/|\()\s*([A-Za-z]{2})\)?$/u', $text, $match) === 1 && PortalText::state($match[2]) !== null) {
            return $this->byName($match[1], $match[2]);
        }

        if (PortalText::state($state) !== null) {
            return $this->byName($text, $state);
        }

        $matches = Municipality::query()->where('name_search', PortalText::normalize($text))->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Suggestions for a typed prefix, at most $limit, the ones that start with the text first.
     *
     * @return Collection<int, Municipality>
     */
    public function suggest(?string $text, ?string $state = null, int $limit = 10): Collection
    {
        $normalized = PortalText::normalize(preg_replace('/\s*(?:-|,|\/)\s*[A-Za-z]{2}$/', '', (string) $text));

        if (mb_strlen($normalized) < 2) {
            return collect();
        }

        $uf = PortalText::state($state);
        $query = Municipality::query()
            ->where(fn ($match) => $match->where('name_search', 'like', $normalized.'%')->orWhere('name_search', 'like', '% '.$normalized.'%'))
            ->when($uf !== null, fn ($scoped) => $scoped->where('state', $uf))
            ->orderByRaw('CASE WHEN name_search = ? THEN 0 WHEN name_search LIKE ? THEN 1 ELSE 2 END', [$normalized, $normalized.'%'])
            ->orderBy('name_search')
            ->orderBy('state')
            ->limit(max(1, min($limit, 20)));

        return $query->get();
    }

    /**
     * Several municipalities by id, in the given order, unknown ids dropped.
     *
     * @param  array<int>  $ids  any order, duplicates and unknown ids allowed
     * @return list<Municipality>
     */
    public function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $found = Municipality::query()->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = [];

        foreach ($ids as $id) {
            if ($found->has($id)) {
                $ordered[] = $found->get($id);
            }
        }

        return $ordered;
    }
}
