<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Services\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $props = [
            'passwordRules' => PasswordPolicy::for($user)->toPasswordRulesString(),
            'status' => $request->session()->get('status'),
            'canManageTwoFactor' => Features::enabled(Features::twoFactorAuthentication()),
        ];

        if ($props['canManageTwoFactor']) {
            $props['twoFactorEnabled'] = $user->hasConfirmedTwoFactor();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['twoFactor'] = [
                'confirmed' => $user->hasConfirmedTwoFactor(),
                'pending' => $user->two_factor_secret !== null && ! $user->hasConfirmedTwoFactor(),
                'required' => $user->is_root_admin,
            ];
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
