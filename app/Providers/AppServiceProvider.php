<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\DnsDomainVerifier;
use App\Services\Websites\DomainVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped so each request and each queued job starts without a previous tenant.
        $this->app->scoped(TenantContext::class);
        $this->app->bind(DomainVerifier::class, DnsDomainVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureTrustedProxies();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure global authorization gates.
     */
    protected function configureGates(): void
    {
        // Platform authority, separate from tenant roles. It never grants access to tenant data.
        Gate::define('platform-admin', fn (User $user): bool => $user->isRootAdmin());
    }

    /**
     * Forwarded headers are trusted only from the proxies listed in configuration.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) config('platform.trusted_proxies')))));

        if ($proxies === []) {
            return;
        }

        Request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    }
}
