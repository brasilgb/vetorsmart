<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Services\Location\MunicipalityDirectory;
use App\Services\Location\ReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public municipality lookups used by the Regional Portal search and the request form of the company sites, on every
 * public host (central, portal and company hostnames), always same-origin.
 *
 * - GET  /localizacao/municipios?busca=salv&uf=RS : suggestions from the IBGE registry (no position involved).
 * - POST /localizacao/municipio {latitude, longitude} : the municipality of the visitor's position.
 *
 * The position is sent in the body, never in the address, and is not stored or logged (ReverseGeocoder). Neither
 * endpoint touches the session or any company record, so they need no CSRF token; both are rate limited.
 */
class LocationController extends Controller
{
    public function suggestions(Request $request, MunicipalityDirectory $municipalities): JsonResponse
    {
        $text = is_string($request->query('busca')) ? mb_substr($request->query('busca'), 0, 80) : '';
        $state = is_string($request->query('uf')) ? $request->query('uf') : null;

        return response()->json([
            'municipalities' => $municipalities->suggest($text, $state)->map(fn (Municipality $municipality): array => $municipality->toPublic())->values()->all(),
        ])->header('Cache-Control', 'public, max-age=86400')->header('X-Robots-Tag', 'noindex');
    }

    public function locate(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->answer(['status' => 'invalid', 'municipality' => null], 422);
        }

        $result = $geocoder->locate((float) $request->input('latitude'), (float) $request->input('longitude'));

        return $this->answer($result, 200);
    }

    /**
     * @param  array{status: string, municipality: Municipality|null}  $result
     */
    private function answer(array $result, int $status): JsonResponse
    {
        return response()->json([
            'status' => $result['status'],
            'municipality' => $result['municipality']?->toPublic(),
        ], $status)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
