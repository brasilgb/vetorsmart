<?php

namespace App\Services\Portal;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Attribution of a visit to the Regional Portal that the browser cannot forge.
 *
 * The portal and the company sites live on different hosts, so the only thing that travels between them is the link.
 * Each portal link to a company carries "ref": the time it was issued and an HMAC of (company, time) with the
 * application key. When the site receives a valid ref for its own company, it records the arrival in its server-side
 * session; the request form and the assistant read that record, never a field sent by the browser. A plain
 * "?origem=portal" typed by hand, a ref of another company, or an expired one is ignored (the visit counts as direct).
 *
 * Nothing about the visitor is stored: only the time of arrival per company, in the visitor's own session.
 */
class PortalReferral
{
    /** Days a portal link stays valid after it was shown. */
    public const int LINK_DAYS = 7;

    /** Hours a recorded arrival keeps attributing requests of that visit. */
    public const int VISIT_HOURS = 24;

    private const string SESSION_KEY = 'portal_referral';

    /**
     * Query string of a portal link to a company: "origem=portal&ref=<time>.<signature>".
     */
    public function query(int $tenantId, ?int $issuedAt = null): string
    {
        return 'origem=portal&ref='.$this->token($tenantId, $issuedAt ?? now()->getTimestamp());
    }

    public function token(int $tenantId, int $issuedAt): string
    {
        return $issuedAt.'.'.$this->signature($tenantId, $issuedAt);
    }

    /**
     * Whether a ref was issued by the portal for this company and is still valid.
     */
    public function isValid(int $tenantId, mixed $ref): bool
    {
        if (! is_string($ref) || preg_match('/^(\d{9,11})\.([a-f0-9]{32})$/', $ref, $parts) !== 1) {
            return false;
        }

        $issuedAt = (int) $parts[1];
        $age = now()->getTimestamp() - $issuedAt;

        return $age >= -300 && $age <= self::LINK_DAYS * 86400 && hash_equals($this->signature($tenantId, $issuedAt), $parts[2]);
    }

    /**
     * Record the arrival when the request carries a valid portal link for this company. Called by every public page
     * a portal link can open.
     */
    public function capture(Request $request, Tenant $tenant): void
    {
        if (! $request->hasSession() || ! $this->isValid((int) $tenant->getKey(), $request->query('ref'))) {
            return;
        }

        $request->session()->put(self::SESSION_KEY.'.'.$tenant->getKey(), ['at' => now()->getTimestamp()]);
    }

    /**
     * Whether this visit to the company came through the portal (recorded on the server, still within the window).
     */
    public function fromPortal(Request $request, Tenant $tenant): bool
    {
        $at = $this->record($request, $tenant)['at'] ?? null;

        return is_int($at) && now()->getTimestamp() - $at <= self::VISIT_HOURS * 3600;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Request $request, Tenant $tenant): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $record = $request->session()->get(self::SESSION_KEY.'.'.$tenant->getKey());

        return is_array($record) ? $record : [];
    }

    private function signature(int $tenantId, int $issuedAt): string
    {
        return substr(hash_hmac('sha256', 'portal-referral|'.$tenantId.'|'.$issuedAt, (string) config('app.key')), 0, 32);
    }
}
