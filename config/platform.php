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
    | Time zone used to present platform-level records (RootAdmin console, audit trail). Instants stay in UTC
    | in the database; companies keep their own time zone for their own screens.
    */
    'display_timezone' => env('PLATFORM_DISPLAY_TIMEZONE', 'America/Sao_Paulo'),

    /*
    | Explicit confirmation of the database used by automated tests and by destructive commands. Both must repeat the
    | exact database name; the name alone is never enough (see App\Support\DatabaseGuard).
    */
    'test_database' => env('VETORSMART_TEST_DATABASE'),
    'destructive_database' => env('VETORSMART_DESTRUCTIVE_DATABASE'),

    /*
    | Scheme used in public site addresses. Use https in production.
    */
    'public_scheme' => env('PLATFORM_PUBLIC_SCHEME', 'https'),

    /*
    | Absolute base address of the official public sites: {public_url}/{segment}/{company}. Every company site lives
    | under this single domain, so no DNS record or subdomain is needed per company. Defaults to APP_URL.
    */
    'public_url' => rtrim((string) env('PLATFORM_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    | First path segments that can never be a segment slug, besides the first segment of every registered route
    | (see App\Services\Websites\ReservedPaths). They cover files and folders served straight from public/.
    */
    'reserved_paths' => [
        'build', 'storage', 'assets', 'images', 'img', 'css', 'js', 'fonts', 'vendor', '.well-known',
        'favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'robots.txt', 'sitemap.xml', 'index.php',
        'www', 'app', 'admin', 'painel', 'site', 'sites', 'empresa', 'empresas', 'segmento', 'segmentos',
    ],

    /*
    | Subdomains a company can never claim, so they cannot impersonate platform services.
    */
    'reserved_subdomains' => [
        'www', 'app', 'admin', 'api', 'mail', 'smtp', 'imap', 'pop', 'pop3', 'ftp', 'sftp', 'cdn', 'static',
        'assets', 'rootadmin', 'root', 'painel', 'dashboard', 'login', 'logout', 'register', 'cadastro', 'entrar',
        'status', 'suporte', 'support', 'ajuda', 'help', 'docs', 'blog', 'webmail', 'mx', 'ns', 'ns1', 'ns2', 'dns',
        'autodiscover', 'autoconfig', 'auth', 'oauth', 'sso', 'id', 'conta', 'contas', 'account', 'billing',
        'pagamento', 'pagamentos', 'cobranca', 'financeiro', 'dev', 'staging', 'homolog', 'homologacao', 'teste',
        'test', 'demo', 'sandbox', 'vetorsmart', 'vetor', 'site', 'sites', 'm', 'mobile', 'localhost', 'n8n',
        'waha', 'phpmyadmin', 'pma', 'mysql', 'db', 'redis', 'grafana', 'metrics', 'monitor', 'health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company subdomains ({name}.<tenant_base_domain>)
    |--------------------------------------------------------------------------
    |
    | Every company gets a subdomain when it is registered. subdomain_sites decides whether that subdomain serves the
    | site (and becomes its canonical address when the company has no custom domain of its own). Keep it off until the
    | wildcard DNS record and the wildcard certificate are live: while off, a subdomain only redirects to the standard
    | address /{segment}/{company}, which is how production has always behaved.
    |
    | max_subdomain_changes: how many times a company may change its subdomain. Every earlier name stays reserved for
    | it and keeps redirecting.
    |
    */

    'subdomain_sites' => (bool) env('PLATFORM_SUBDOMAIN_SITES', false),

    'max_subdomain_changes' => (int) env('PLATFORM_MAX_SUBDOMAIN_CHANGES', 3),

    /*
    | Abuse limit: domains (subdomain and custom) per company.
    */
    'max_domains_per_tenant' => (int) env('PLATFORM_MAX_DOMAINS_PER_TENANT', 3),

    /*
    | Proxies whose forwarded headers are trusted (comma-separated IPs). Empty means
    | forwarded headers are ignored.
    */
    'trusted_proxies' => env('PLATFORM_TRUSTED_PROXIES', ''),

    /*
    | Commercial plans, cycle prices and additional users are not configured here: they live in the database (plans
    | and subscriptions) and are managed in the RootAdmin, with contracted terms frozen per subscription.
    */

    /*
    |--------------------------------------------------------------------------
    | Billing enforcement
    |--------------------------------------------------------------------------
    |
    | Whether subscriptions are enforced at all (trial end, grace, suspension, charges, paid seats and credit
    | purchases). Separate from the four billing locks (Efí environment, approved prices, gateway credentials and the
    | credit purchase switch): those decide whether a real charge can reach Efí; this one decides whether the
    | subscription lifecycle applies. Only the literal value `true` enables it; missing or anything else keeps it off.
    | Even when enabled, enforcement starts only at the date recorded by the audited billing:start-enforcement command.
    |
    */
    'billing_enforcement_enabled' => env('PLATFORM_BILLING_ENFORCEMENT_ENABLED', false) === true,

    /*
    | Optional end (Y-m-d) of the legacy access for accounts that existed before e-mail confirmation. Empty or invalid:
    | no deadline, so no existing user is locked out unexpectedly (for example while SMTP is not operational).
    */
    'legacy_email_grace_until' => env('PLATFORM_LEGACY_EMAIL_GRACE_UNTIL'),
];
