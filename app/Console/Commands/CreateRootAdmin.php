<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateRootAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * The password is never an argument. It comes from a hidden prompt, from the
     * environment variable named by --password-env, or from standard input.
     *
     * @var string
     */
    protected $signature = 'platform:root-admin
        {email? : E-mail do administrador da plataforma (solicitado se omitido em terminal interativo)}
        {--name= : Nome completo, ao criar uma conta nova}
        {--password-env= : Nome da variável de ambiente que contém a senha (modo sem terminal)}
        {--password-stdin : Lê a senha da entrada padrão (modo sem terminal)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provisiona ou promove um RootAdmin da plataforma. A senha é oculta, vem de variável de ambiente ou da entrada padrão.';

    /**
     * Execute the console command.
     */
    public function handle(AuditLogger $auditLogger): int
    {
        $email = $this->resolveEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $emailErrors = Validator::make(['email' => $email], ['email' => ['required', 'email']])->errors();

        if ($emailErrors->isNotEmpty()) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            // An existing account is promoted only: its credentials are never replaced here.
            $user->forceFill(['is_root_admin' => true])->save();
            $created = false;
        } else {
            $name = (string) ($this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Nome completo') : ''));

            $password = $this->resolvePassword();

            if ($password === null) {
                return self::FAILURE;
            }

            $passwordErrors = Validator::make(
                ['name' => $name, 'password' => $password],
                ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', Password::min(8)]],
            )->errors();

            if ($passwordErrors->isNotEmpty()) {
                $this->error('Nome ou senha inválidos. Informe um nome e uma senha de ao menos 8 caracteres.');

                return self::FAILURE;
            }

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
            ]);
            $user->is_root_admin = true;
            $user->save();
            $created = true;
        }

        // The password is never passed to the audit log, the output or any log channel.
        $auditLogger->record('platform.root_admin_provisioned', null, $user, [
            'created' => $created,
        ], $user);

        $this->info($created ? 'RootAdmin criado.' : 'Usuário promovido a RootAdmin.');

        return self::SUCCESS;
    }

    /**
     * Obtain the e-mail from the argument or, in an interactive terminal, from a prompt.
     * Returns null when no e-mail is available.
     */
    private function resolveEmail(): ?string
    {
        $email = trim((string) $this->argument('email'));

        if ($email === '' && $this->input->isInteractive()) {
            $email = trim((string) $this->ask('E-mail do administrador da plataforma'));
        }

        if ($email === '') {
            $this->error('Informe o e-mail: php artisan platform:root-admin {email}');

            return null;
        }

        return Str::lower($email);
    }

    /**
     * Obtain the password from the safest available source, or null when none is provided.
     */
    private function resolvePassword(): ?string
    {
        $envName = $this->option('password-env');

        if (is_string($envName) && $envName !== '') {
            $value = getenv($envName);

            if ($value === false || $value === '') {
                $this->error('A variável de ambiente informada não contém senha.');

                return null;
            }

            return $value;
        }

        if ($this->option('password-stdin')) {
            $line = fgets(STDIN);

            return $line === false ? null : rtrim($line, "\r\n");
        }

        if ($this->input->isInteractive()) {
            return $this->secret('Senha (mínimo 8 caracteres)');
        }

        $this->error('Sem terminal interativo: informe --password-env=NOME ou --password-stdin.');

        return null;
    }
}
