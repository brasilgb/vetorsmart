<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    /**
     * Indicate that the user is a platform root admin.
     */
    public function rootAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_root_admin' => true,
        ]);
    }

    /**
     * Indicate that the user has a confirmed TOTP factor with recovery codes, stored encrypted as Fortify does.
     */
    public function withConfirmedTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(self::TWO_FACTOR_SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(self::TWO_FACTOR_RECOVERY_CODES)),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Base32 secret used by test fixtures. Never used outside tests.
     */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * @var list<string>
     */
    public const array TWO_FACTOR_RECOVERY_CODES = ['testcode1-aaaa', 'testcode2-bbbb'];
}
