<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * Password strength for every account: at least 8 characters of any kind (digits only is accepted).
 * Global administrators are protected by their mandatory second factor, not by a stricter password.
 */
class PasswordPolicy
{
    /**
     * Rule for the given account. A null user means a new account (registration).
     */
    public static function for(?User $user): Password
    {
        return Password::default();
    }
}
