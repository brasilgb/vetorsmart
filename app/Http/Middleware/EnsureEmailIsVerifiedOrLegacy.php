<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `verified` gate of the panel and the tenant API. A confirmed address passes; an account that existed before
 * e-mail confirmation (legacy access, see User::hasLegacyEmailAccess) also passes, so no existing user is locked out
 * on deploy. Every other account goes through the normal confirmation, exactly as Laravel's EnsureEmailIsVerified
 * (a redirect to the confirmation screen, or 403 for JSON). Sensitive operations use `verified.strict`, which is
 * Laravel's own middleware and accepts only a real confirmation.
 */
class EnsureEmailIsVerifiedOrLegacy
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasLegacyEmailAccess()) {
            return $next($request);
        }

        if (! $user instanceof User || ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                abort(403, 'Your email address is not verified.');
            }

            return Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
