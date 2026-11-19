<?php

namespace App\Http\Requests\Site;

use App\Services\Assistant\AssistantText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request form of a public company site. The company comes from the address, never from this payload; the chosen
 * service is checked against the services of the published version by the controller.
 */
class StoreSiteServiceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'service_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
            'origem' => ['nullable', 'string', 'in:portal'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('phone')) {
                    return;
                }

                if ($this->phoneDigits() === null) {
                    $validator->errors()->add('phone', 'Informe um telefone com DDD.');
                }
            },
        ];
    }

    /**
     * The phone as digits only (DDD and number), or null when it is not a Brazilian phone.
     */
    public function phoneDigits(): ?string
    {
        return app(AssistantText::class)->phone((string) $this->input('phone'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone', 'email' => 'e-mail', 'description' => 'descrição', 'consent' => 'autorização de contato'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['consent.accepted' => 'Autorize o contato para enviar a solicitação.'];
    }
}
