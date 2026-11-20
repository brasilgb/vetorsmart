<?php

namespace App\Services\Websites;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Campaign tags (utm_source, utm_medium, utm_campaign) of a visit to a company's public pages, kept as complementary
 * information of the commercial origin.
 *
 * - First touch of the visit: the first tagged page the visitor opened for that company is kept; later tagged pages
 *   do not replace it within the visit window.
 * - Sanitised: lowercase, only letters, digits, dot, dash and underscore, 100 characters at most; anything else is
 *   dropped. No personal data is read or kept.
 * - Declared by the link only: tags never prove a privileged origin (the Regional Portal comes only from PortalReferral)
 *   and never carry a company, a status or a permission.
 */
class CampaignTags
{
    /** Hours the tags of a visit keep attributing its requests (same window as the portal arrival). */
    public const int VISIT_HOURS = 24;

    private const string SESSION_KEY = 'campaign_tags';

    private const array FIELDS = ['utm_source', 'utm_medium', 'utm_campaign'];

    /**
     * Keep the tags of this page when the visit has none yet for the company.
     */
    public function capture(Request $request, Tenant $tenant): void
    {
        if (! $request->hasSession() || $this->current($request, $tenant) !== []) {
            return;
        }

        $tags = $this->sanitise([
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
        ]);

        if ($tags['utm_source'] === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY.'.'.$tenant->getKey(), [...$tags, 'at' => now()->getTimestamp()]);
    }

    /**
     * The tags of the current visit to the company, or an empty array.
     *
     * @return array{utm_source?: string, utm_medium?: string|null, utm_campaign?: string|null}
     */
    public function current(Request $request, Tenant $tenant): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $record = $request->session()->get(self::SESSION_KEY.'.'.$tenant->getKey());

        if (! is_array($record) || ! is_int($record['at'] ?? null) || now()->getTimestamp() - $record['at'] > self::VISIT_HOURS * 3600) {
            return [];
        }

        $tags = $this->sanitise($record);

        return $tags['utm_source'] === null ? [] : $tags;
    }

    /**
     * Only well-formed values survive; a missing source means no campaign at all.
     *
     * @param  array<string, mixed>  $values
     * @return array{utm_source: string|null, utm_medium: string|null, utm_campaign: string|null}
     */
    public function sanitise(array $values): array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $value = $values[$field] ?? null;
            $value = is_string($value) ? mb_strtolower(trim($value)) : '';
            $clean[$field] = preg_match('/^[a-z0-9._-]{1,100}$/', $value) === 1 ? $value : null;
        }

        return $clean;
    }
}
