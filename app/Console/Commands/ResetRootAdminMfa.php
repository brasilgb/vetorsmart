<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operator recovery for a global administrator who lost every second factor.
 *
 * Runs only from the server shell: there is no HTTP route for it. It revokes the TOTP factor and the recovery codes,
 * ends the administrator's sessions and rotates the remember token, so the next sign-in must enroll a new factor.
 * The password is not changed here. The identity check and the authorization happen outside the system, before the command.
 */
class ResetRootAdminMfa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:mfa-reset
        {email : E-mail do administrador global afetado}
        {--reason= : Justificativa obrigatória (mínimo de 20 caracteres), registrada na auditoria}
        {--confirm-email= : Repita o e-mail para confirmar a operação (obrigatório sem interação)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoga o MFA de um administrador global, encerra as sessões dele e exige novo enrollment no próximo acesso.';

    /**
     * Execute the console command.
     */
    public function handle(AuditLogger $auditLogger): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $reason = trim((string) $this->option('reason'));

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_root_admin) {
            $this->error('Nenhum administrador global encontrado com este e-mail. Nenhuma alteração foi feita.');

            return self::FAILURE;
        }

        if (mb_strlen($reason) < 20) {
            $this->error('A justificativa é obrigatória e deve ter pelo menos 20 caracteres (--reason).');

            return self::FAILURE;
        }

        if (! $this->confirmed($email)) {
            $this->error('A confirmação não corresponde ao e-mail informado. Nenhuma alteração foi feita.');

            return self::FAILURE;
        }

        $sessionsRevoked = DB::transaction(function () use ($user, $auditLogger, $reason): int {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            $sessionsRevoked = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();

            $auditLogger->record('mfa.reset_by_operator', null, $user, [
                'reason' => $reason,
                'sessions_revoked' => $sessionsRevoked,
            ]);

            return $sessionsRevoked;
        });

        if (config('session.driver') !== 'database') {
            $this->warn('Sessões não estão na tabela do banco (SESSION_DRIVER diferente de database). Encerre-as pelo mecanismo do driver.');
        }

        $this->info("MFA de {$user->name} revogado. Sessões encerradas no banco: {$sessionsRevoked}.");
        $this->line('No próximo acesso, o administrador deverá configurar um novo aplicativo autenticador.');

        return self::SUCCESS;
    }

    /**
     * Interactive runs ask the operator to type the e-mail; non-interactive runs must pass it with --confirm-email.
     */
    private function confirmed(string $email): bool
    {
        $typed = $this->input->isInteractive()
            ? $this->ask("Digite {$email} para confirmar a revogação do MFA")
            : $this->option('confirm-email');

        return Str::lower(trim((string) $typed)) === $email;
    }
}
