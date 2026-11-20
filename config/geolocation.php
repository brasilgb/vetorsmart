<?php

/*
|--------------------------------------------------------------------------
| "Usar minha localização"
|--------------------------------------------------------------------------
|
| Reverse geocoding turns the visitor's position into a municipality of the IBGE registry. The browser rounds the
| position to 3 decimal places (about 110 m) before sending it, the server rounds it again, sends it only to the
| provider below and keeps nothing: no log, no cache, no metric holds coordinates. Only the municipality found is
| returned and, once the visitor confirms it, stored with the request.
|
| provider: "nominatim" (OpenStreetMap, public instance or a self-hosted one) or "none" (the button is not shown and
| visitors choose the municipality by hand). The public Nominatim instance accepts at most 1 request per second per
| application and requires an identifying User-Agent; global_per_minute keeps the platform under that limit, and a
| visitor that hits it is asked to choose the municipality by hand.
|
*/

return [
    'provider' => env('GEOLOCATION_PROVIDER', 'nominatim'),

    'nominatim_url' => env('GEOLOCATION_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),

    'user_agent' => env('GEOLOCATION_USER_AGENT', 'VetorSmart/1.0 (+https://vetorsmart.com.br)'),

    'timeout' => (int) env('GEOLOCATION_TIMEOUT', 5),

    // Requests per minute for the whole platform (public Nominatim: at most 1 per second).
    'global_per_minute' => (int) env('GEOLOCATION_GLOBAL_PER_MINUTE', 50),

    // Requests per minute for one visitor (IP).
    'visitor_per_minute' => (int) env('GEOLOCATION_VISITOR_PER_MINUTE', 6),
];
