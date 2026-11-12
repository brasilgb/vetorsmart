<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps global administrators out of the RootAdmin area until a second factor is confirmed.
 *
 * Without a confirmed TOTP, the session is sent to the security settings, where the factor is configured.
 * This check runs before the platform-admin gate; the gate repeats the requirement, so no single layer decides alone.
 */
class EnsureRootAdminHasTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $user->is_root_admin && $user->isActive() && ! $user->hasConfirmedTwoFactor()) {
            return redirect()
                ->route('security.edit')
                ->with('status', 'Configure a autenticação em dois fatores para acessar a administração da plataforma.');
        }

        return $next($request);
    }
}
