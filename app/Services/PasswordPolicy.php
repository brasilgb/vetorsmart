<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * Password strength by account type. Common users follow the simple default
 * (Password::defaults: at least 8 characters of any kind). Global administrators
 * keep the strict policy, on top of their mandatory second factor.
 */
class PasswordPolicy
{
    /**
     * Rule for the given account. A null user means a new account (registration).
     */
    public static function for(?User $user): Password
    {
        if ($user?->is_root_admin !== true) {
            return Password::default();
        }

        $rule = Password::min(12)->letters()->mixedCase()->numbers()->symbols();

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }
}
