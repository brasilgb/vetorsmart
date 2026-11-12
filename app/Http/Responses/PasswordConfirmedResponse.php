<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;

/**
 * Returns the user to the area they were in after confirming the password.
 *
 * A global administrator returns to a settings page only when that page was the intended URL; otherwise to the console,
 * which applies the MFA rules. Other profiles keep the intended URL. A stale intended URL (for example the public landing)
 * must not send an administrator to the wrong place.
 */
class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    /**
     * @return RedirectResponse|JsonResponse
     */
    public function toResponse($request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->is_root_admin) {
            $target = $this->administratorTarget($request);

            return $request->expectsJson()
                ? response()->json(['redirect' => $target])
                : redirect()->to($target);
        }

        return $request->expectsJson()
            ? response()->json(['redirect' => url()->previous(route('dashboard'))])
            : redirect()->intended(route('dashboard'));
    }

    /**
     * The administrator keeps a stored settings page (for example the security page that asked for the password),
     * and otherwise goes to the console. Any other stored URL is discarded.
     */
    private function administratorTarget(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $path = is_string($intended) ? (parse_url($intended, PHP_URL_PATH) ?: '') : '';

        return str_starts_with($path, '/settings/') ? $intended : route('rootadmin.dashboard');
    }
}
