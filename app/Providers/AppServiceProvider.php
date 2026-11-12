<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Assistant\AssistantProvider;
use App\Services\Assistant\Providers\NullAssistantProvider;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\DnsDomainVerifier;
use App\Services\Websites\DomainVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // No language-model provider is bundled or assumed. The deterministic flow runs with the null provider.
        $this->app->bind(AssistantProvider::class, NullAssistantProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureTrustedProxies();
        $this->configureRateLimiters();
    }

    /**
     * Public visitor routes: limited per client and per company hostname, so one visitor cannot flood a company's chat.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('assistant-public', fn (Request $request): Limit => Limit::perMinute((int) config('assistant.public_requests_per_minute', 30))
            ->by($request->ip().'|'.$request->getHost()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // The product is Portuguese (Brazil). Strings missing in lang/pt_BR fall back to English.
        app()->setLocale('pt_BR');

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
        // Requires a confirmed second factor as well, so a password alone never grants platform access.
        Gate::define('platform-admin', fn (User $user): bool => $user->isRootAdmin() && $user->hasConfirmedTwoFactor());
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
