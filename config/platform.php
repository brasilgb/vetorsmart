<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform hosts
    |--------------------------------------------------------------------------
    |
    | central_host: the commercial and administrative domain (landing, login, panel).
    | tenant_base_domain: the parent domain of tenant subdomains (e.g. empresa.<base>).
    | Both come from the environment, so the definitive domain is never presumed here.
    |
    */

    'central_host' => env('PLATFORM_CENTRAL_HOST', 'localhost'),

    'tenant_base_domain' => env('PLATFORM_TENANT_BASE_DOMAIN', 'vetorsmart.test'),

    /*
    | Scheme used in public site addresses. Use https in production.
    */
    'public_scheme' => env('PLATFORM_PUBLIC_SCHEME', 'https'),

    /*
    | Subdomains a company can never claim, so they cannot impersonate platform services.
    */
    'reserved_subdomains' => [
        'www', 'app', 'admin', 'api', 'mail', 'smtp', 'imap', 'ftp', 'cdn', 'static',
        'assets', 'rootadmin', 'painel', 'dashboard', 'login', 'register', 'status', 'suporte',
    ],

    /*
    | Abuse limit: domains (subdomain and custom) per company.
    */
    'max_domains_per_tenant' => (int) env('PLATFORM_MAX_DOMAINS_PER_TENANT', 3),

    /*
    | Proxies whose forwarded headers are trusted (comma-separated IPs). Empty means
    | forwarded headers are ignored.
    */
    'trusted_proxies' => env('PLATFORM_TRUSTED_PROXIES', ''),
];
