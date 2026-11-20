<?php

namespace App\Services\Location;

use App\Models\Municipality;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * The municipality a visitor confirmed (in the portal or in a company site), carried to the request form of the
 * company. Only the IBGE code travels between pages ("?municipio=4316501") and only the code is kept, in the visitor's
 * own session and per company. The visitor can always change it in the form. It never decides the company, the
 * service or the attribution of the visit; it only fills the municipality field and checks the coverage.
 */
class VisitorMunicipality
{
    private const string SESSION_KEY = 'visitor_municipality';

    public function __construct(private MunicipalityDirectory $municipalities) {}

    /**
     * Keep the municipality of the link ("municipio" query parameter) for this company, when it is a real one.
     */
    public function capture(Request $request, Tenant $tenant): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $municipality = $this->municipalities->find($request->query('municipio'));

        if ($municipality !== null) {
            $this->remember($request, $tenant, $municipality);
        }
    }

    public function remember(Request $request, Tenant $tenant, Municipality $municipality): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY.'.'.$tenant->getKey(), (int) $municipality->id);
        }
    }

    public function current(Request $request, Tenant $tenant): ?Municipality
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->municipalities->find($request->session()->get(self::SESSION_KEY.'.'.$tenant->getKey()));
    }
}
