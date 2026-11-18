<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Segment;
use App\Models\User;
use App\Services\TenantRegistrationService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Registration attempts one IP address may make: a short burst, and a total per hour. An office behind one
     * address can still register a few companies; a script cannot create them in bulk.
     *
     * @var array<string, array{attempts: int, seconds: int}>
     */
    private const array LIMITS = [
        'register:' => ['attempts' => 5, 'seconds' => 60],
        'register-hour:' => ['attempts' => 20, 'seconds' => 3600],
    ];

    /**
     * Company names that start with the homologation prefix, in any case and spacing ("[HOMOLOG]", "[ homolog-x ]").
     */
    private const string RESERVED_NAME_PATTERN = '/^\s*\[\s*homolog/iu';

    public function __construct(private TenantRegistrationService $registration) {}

    /**
     * Validate the input and create the company together with its owner. The account starts unverified, and the
     * private areas stay closed until the e-mail is confirmed.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->ensureRegistrationAttemptAllowed();
        $this->rejectAutomatedSubmission($input);

        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255', 'not_regex:'.self::RESERVED_NAME_PATTERN],
            'segment_id' => ['required', 'integer', Rule::exists(Segment::class, 'id')->where('active', true)],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ], [
            'company_name.not_regex' => 'Este nome é reservado. Escolha outro nome para a empresa.',
        ])->validate();

        return $this->registration->register([
            'company_name' => $input['company_name'],
            'segment_id' => (int) $input['segment_id'],
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Limit how many registrations one IP address can attempt per minute and per hour.
     *
     * @throws ValidationException
     */
    private function ensureRegistrationAttemptAllowed(): void
    {
        $ip = (string) request()->ip();

        foreach (self::LIMITS as $prefix => $limit) {
            if (RateLimiter::tooManyAttempts($prefix.$ip, $limit['attempts'])) {
                throw ValidationException::withMessages([
                    'email' => $limit['seconds'] === 60
                        ? 'Muitas tentativas de cadastro. Aguarde um minuto e tente novamente.'
                        : 'Muitas tentativas de cadastro a partir desta rede. Tente novamente mais tarde.',
                ]);
            }
        }

        foreach (self::LIMITS as $prefix => $limit) {
            RateLimiter::hit($prefix.$ip, $limit['seconds']);
        }
    }

    /**
     * The form has a field that people do not see or fill in. A value in it means an automated submission, which is
     * refused with a neutral message that does not explain the check.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    private function rejectAutomatedSubmission(array $input): void
    {
        if (filled($input['website'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Não foi possível concluir o cadastro. Tente novamente.',
            ]);
        }
    }
}
