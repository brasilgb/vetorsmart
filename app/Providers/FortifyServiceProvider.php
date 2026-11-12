<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\UserStatus;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordConfirmedResponse;
use App\Models\Segment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);

        // The second factor completes the same sign-in, so it must send each profile to its own area too.
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);

        // Confirming the password must not send an administrator to a stale intended URL such as the public landing.
        $this->app->singleton(PasswordConfirmedResponseContract::class, PasswordConfirmedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureTwoFactorAudit();
    }

    /**
     * Two-factor audit: records changes and failed challenges of administrators. Secrets and codes are never
     * passed to the audit log, only the action and the account that performed it.
     */
    private function configureTwoFactorAudit(): void
    {
        $changes = [
            TwoFactorAuthenticationEnabled::class => 'mfa.enabled',
            TwoFactorAuthenticationConfirmed::class => 'mfa.confirmed',
            TwoFactorAuthenticationDisabled::class => 'mfa.disabled',
            RecoveryCodesGenerated::class => 'mfa.recovery_codes_regenerated',
        ];

        foreach ($changes as $event => $action) {
            Event::listen($event, function (object $payload) use ($action): void {
                /** @var User $user */
                $user = $payload->user;

                app(AuditLogger::class)->record($action, null, null, [], $user);
            });
        }

        Event::listen(TwoFactorAuthenticationFailed::class, function (object $payload): void {
            /** @var User $user */
            $user = $payload->user;

            if ($user->is_root_admin) {
                app(AuditLogger::class)->record('mfa.challenge_failed', null, null, [], $user);
            }
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Only active accounts can sign in. Suspended accounts receive the same
     * generic failure as a wrong password, so the status is not disclosed.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('email', Str::lower((string) $request->input(Fortify::username())))
                ->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user->status === UserStatus::Active ? $user : null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'segments' => Segment::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Keyed by the pending login, so guessing codes is limited per attempted sign-in.
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id', $request->ip()));
        });
    }
}
