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

    public function __construct(private TenantRegistrationService $registration) {}

    /**
     * Validate the input and create the company together with its owner.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->ensureRegistrationAttemptAllowed();

        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            'segment_id' => ['required', 'integer', Rule::exists(Segment::class, 'id')->where('active', true)],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
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
     * Limit how many registrations one IP address can attempt per minute.
     *
     * @throws ValidationException
     */
    private function ensureRegistrationAttemptAllowed(): void
    {
        $allowed = RateLimiter::attempt('register:'.request()->ip(), 5, fn () => true, 60);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas de cadastro. Aguarde um minuto e tente novamente.',
            ]);
        }
    }
}
