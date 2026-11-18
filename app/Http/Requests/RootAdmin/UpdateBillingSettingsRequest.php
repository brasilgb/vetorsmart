<?php

namespace App\Http\Requests\RootAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Billing rules and Efí credentials. Credential fields are write-only: an empty field keeps the stored value, so
 * the screen never needs to show a secret back.
 */
class UpdateBillingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'efi_environment' => ['required', 'in:homologation,production'],
            'efi_pix_client_id' => ['nullable', 'string', 'max:255'],
            'efi_pix_client_secret' => ['nullable', 'string', 'max:255'],
            'efi_pix_certificate' => ['nullable', 'string', 'max:20000', 'regex:/-----BEGIN [A-Z ]+-----/'],
            'efi_pix_key' => ['nullable', 'string', 'max:120'],
            'efi_charges_client_id' => ['nullable', 'string', 'max:255'],
            'efi_charges_client_secret' => ['nullable', 'string', 'max:255'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:30'],
            'renewal_days_before' => ['required', 'integer', 'min:1', 'max:30'],
            'billet_due_days' => ['required', 'integer', 'min:1', 'max:30'],
            'confirm_production' => ['accepted_if:efi_environment,production'],
            'ai_tokens_per_credit' => ['required', 'integer', 'min:1', 'max:10000000'],
            'ai_low_balance_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'ai_credit_purchases_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'efi_pix_certificate.regex' => 'Cole o certificado em formato PEM (convertido do .p12 da Efí).',
            'confirm_production.accepted_if' => 'Confirme que o ambiente de produção gera cobranças reais.',
        ];
    }
}
