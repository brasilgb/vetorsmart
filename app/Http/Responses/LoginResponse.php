<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Sends each profile to its own area. The intended URL stored in the session is
 * deliberately ignored, so a crafted link cannot redirect a user to an external site
 * or to an area the profile does not own.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @return RedirectResponse|JsonResponse
     */
    public function toResponse($request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $this->destinationFor($user)]);
        }

        return redirect()->to($this->destinationFor($user));
    }

    private function destinationFor(?User $user): string
    {
        return $user?->isRootAdmin() ? route('rootadmin.dashboard') : route('dashboard');
    }
}
