<?php

namespace App\Services\Location;

use App\Models\Municipality;
use App\Services\Portal\PortalText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Municipality of a position, for "Usar minha localização".
 *
 * The coordinates are used only to identify the municipality: they are rounded to 3 decimal places, sent to the
 * configured provider and discarded. They are never logged (a provider failure is logged by its kind only, without the
 * request address), never cached and never returned. The answer of the provider is matched to the IBGE registry by
 * name and state; anything that does not match exactly one municipality is "ambiguous" and the visitor chooses by hand.
 */
class ReverseGeocoder
{
    public const string FOUND = 'found';

    public const string AMBIGUOUS = 'ambiguous';

    public const string OUTSIDE = 'outside';

    public const string UNAVAILABLE = 'unavailable';

    public function __construct(private MunicipalityDirectory $municipalities) {}

    public function enabled(): bool
    {
        return config('geolocation.provider') === 'nominatim';
    }

    /**
     * @return array{status: string, municipality: Municipality|null}
     */
    public function locate(float $latitude, float $longitude): array
    {
        if (! $this->enabled()) {
            return ['status' => self::UNAVAILABLE, 'municipality' => null];
        }

        // Rough bounding box of Brazil: a position elsewhere never reaches the provider.
        if ($latitude < -34.0 || $latitude > 5.5 || $longitude < -74.5 || $longitude > -28.5) {
            return ['status' => self::OUTSIDE, 'municipality' => null];
        }

        if (! RateLimiter::attempt('geolocation:global', max(1, (int) config('geolocation.global_per_minute', 50)), fn (): bool => true, 60)) {
            return ['status' => self::UNAVAILABLE, 'municipality' => null];
        }

        try {
            $response = Http::timeout(max(1, (int) config('geolocation.timeout', 5)))
                ->withHeaders(['User-Agent' => (string) config('geolocation.user_agent'), 'Accept-Language' => 'pt-BR'])
                ->get(rtrim((string) config('geolocation.nominatim_url'), '/').'/reverse', [
                    'format' => 'jsonv2',
                    'lat' => number_format(round($latitude, 3), 3, '.', ''),
                    'lon' => number_format(round($longitude, 3), 3, '.', ''),
                    'zoom' => 10,
                    'addressdetails' => 1,
                ]);
        } catch (Throwable $exception) {
            Log::warning('geolocation.provider_failed', ['reason' => class_basename($exception)]);

            return ['status' => self::UNAVAILABLE, 'municipality' => null];
        }

        if (! $response->successful()) {
            Log::warning('geolocation.provider_failed', ['reason' => 'http_'.$response->status()]);

            return ['status' => self::UNAVAILABLE, 'municipality' => null];
        }

        $address = $response->json('address');

        if (! is_array($address)) {
            return ['status' => self::AMBIGUOUS, 'municipality' => null];
        }

        if (($address['country_code'] ?? null) !== 'br') {
            return ['status' => self::OUTSIDE, 'municipality' => null];
        }

        $state = $this->state($address);

        if ($state === null) {
            return ['status' => self::AMBIGUOUS, 'municipality' => null];
        }

        foreach (['city', 'town', 'village', 'municipality', 'city_district'] as $field) {
            $name = $address[$field] ?? null;
            $municipality = is_string($name) ? $this->municipalities->byName($name, $state) : null;

            if ($municipality !== null) {
                return ['status' => self::FOUND, 'municipality' => $municipality];
            }
        }

        return ['status' => self::AMBIGUOUS, 'municipality' => null];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function state(array $address): ?string
    {
        $iso = $address['ISO3166-2-lvl4'] ?? null;

        if (is_string($iso) && preg_match('/^BR-([A-Z]{2})$/', $iso, $match) === 1) {
            return PortalText::state($match[1]);
        }

        $name = PortalText::normalize(is_string($address['state'] ?? null) ? $address['state'] : null);

        foreach (PortalText::STATE_NAMES as $uf => $stateName) {
            if (PortalText::normalize($stateName) === $name) {
                return $uf;
            }
        }

        return null;
    }
}
