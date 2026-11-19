<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Platform actions on a person's account (all companies at once): suspend and reactivate the sign-in, and resend
 * the e-mail confirmation link. Every action is audited. A RootAdmin cannot suspend their own account, nor the last
 * active RootAdmin. Company roles and memberships are not changed here: that stays with each company's owners.
 */
class UserAccountController extends Controller
{
    public function suspend(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], ['reason.*' => 'Informe o motivo da suspensão (5 a 300 caracteres).']);
        $actor = $this->actor($request);

        DB::transaction(function () use ($user, $actor, $data, $auditLogger): void {
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($user->is($actor)) {
                throw ValidationException::withMessages(['reason' => 'Você não pode suspender a própria conta.']);
            }

            if (! $user->isActive()) {
                return;
            }

            if ($user->is_root_admin && User::query()->where('is_root_admin', true)->where('status', UserStatus::Active->value)->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages(['reason' => 'Esta é a última conta RootAdmin ativa e não pode ser suspensa.']);
            }

            $user->forceFill(['status' => UserStatus::Suspended])->save();

            // Open sessions end at once; EnsureAccountIsActive also refuses any request that is already in flight.
            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            }

            $auditLogger->record('platform.user_suspended', null, $user, ['reason' => $data['reason']], $actor);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conta suspensa. O acesso foi encerrado em todas as empresas.']);

        return back();
    }

    public function reactivate(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $user->isActive()) {
            $user->forceFill(['status' => UserStatus::Active])->save();
            $auditLogger->record('platform.user_reactivated', null, $user, [], $this->actor($request));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conta reativada.']);

        return back();
    }

    /**
     * Send a new confirmation link to an account whose address is not confirmed (including legacy accounts).
     */
    public function resendVerification(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['user' => 'Este e-mail já está confirmado.']);
        }

        $user->sendEmailVerificationNotification();
        $auditLogger->record('platform.verification_resent', null, $user, [], $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link de confirmação enviado para o e-mail da conta.']);

        return back();
    }
}
