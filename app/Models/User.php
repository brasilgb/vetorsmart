<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $email_verification_legacy_at
 * @property string $password
 * @property UserStatus $status
 * @property bool $is_root_admin
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Determine whether the account may sign in and act.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Determine whether the user holds the global platform authorization.
     *
     * Not mass-assignable: it can only be granted by the root admin provisioning command.
     */
    public function isRootAdmin(): bool
    {
        return $this->is_root_admin && $this->isActive();
    }

    /**
     * Legacy access: the account existed before e-mail confirmation started and has not confirmed its address yet. It
     * keeps the access it already had to the panel (no lockout on deploy), but it is NOT a confirmed address: sensitive
     * operations (RootAdmin, billing, team management) still require the real confirmation. It ends when the address
     * is confirmed, when the e-mail is changed, or after PLATFORM_LEGACY_EMAIL_GRACE_UNTIL, when that date is set.
     */
    public function hasLegacyEmailAccess(): bool
    {
        if ($this->hasVerifiedEmail() || $this->email_verification_legacy_at === null) {
            return false;
        }

        $graceUntil = config('platform.legacy_email_grace_until');

        if (! is_string($graceUntil) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $graceUntil) !== 1) {
            return true;
        }

        return now()->lte(Carbon::parse($graceUntil)->endOfDay());
    }

    /**
     * Send the e-mail confirmation link through the queue (see VerifyEmailNotification).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Send the password reset link through the queue (see ResetPasswordNotification).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return BelongsToMany<Tenant, $this, TenantUser>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantUser::class)
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_legacy_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_root_admin' => 'boolean',
        ];
    }
}
