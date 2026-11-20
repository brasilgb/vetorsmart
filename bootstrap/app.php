<?php

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerifiedOrLegacy;
use App\Http\Middleware\EnsureSubscriptionAllowsAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SiteHostGuard;
use App\Services\Websites\TrustedHosts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only the central host, base-domain subdomains and verified custom domains are accepted.
        // Any other Host header is rejected before it reaches routing or the database.
        $middleware->trustHosts(at: fn (): array => app(TrustedHosts::class)->patterns());

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // "Usar minha localização" only answers which municipality a position is in: no session, no record, no company.
        $middleware->validateCsrfTokens(except: ['localizacao/municipio']);

        // Every response, including errors and the API.
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ContentSecurityPolicy::class);

        $middleware->web(prepend: [
            SiteHostGuard::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureAccountIsActive::class,
        ]);

        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'subscription.access' => EnsureSubscriptionAllowsAccess::class,
            // Accounts created before e-mail confirmation keep their access (legacy); sensitive areas use the strict gate.
            'verified' => EnsureEmailIsVerifiedOrLegacy::class,
            'verified.strict' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A confirmation link that is expired, tampered with or issued for another account never confirms anything:
        // the person goes back to the confirmation screen with an explanation and can ask for a new link.
        $exceptions->render(function (InvalidSignatureException|AuthorizationException|AccessDeniedHttpException $exception, Request $request) {
            if (! $request->routeIs('verification.verify') || $request->expectsJson()) {
                return null;
            }

            return redirect()->route('verification.notice')->with('verification_error', 'Este link de confirmação é inválido, expirou ou pertence a outra conta. Peça um novo link abaixo.');
        });
    })->create();
