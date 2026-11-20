<?php

namespace App\Http\Requests\Site;

use App\Models\Municipality;
use App\Services\Assistant\AssistantText;
use App\Services\Location\MunicipalityDirectory;
use App\Services\Portal\PortalText;
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
            'envio' => ['nullable', 'string', 'size:40', 'alpha_num:ascii'],
            'municipality' => ['required', 'string', 'max:80'],
            'municipality_id' => ['nullable', 'integer'],
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
            function (Validator $validator): void {
                if (! $validator->errors()->has('municipality') && $this->municipality() === null) {
                    $validator->errors()->add('municipality', 'Escolha o município na lista, por exemplo: Salvador do Sul - RS.');
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
     * The municipality chosen in the form: the registry entry of the selected suggestion when its name is still the one
     * in the field, otherwise the text read as "Município - UF". Null when it is not a municipality of the registry.
     */
    public function municipality(): ?Municipality
    {
        $directory = app(MunicipalityDirectory::class);
        $text = (string) $this->input('municipality');
        $chosen = $directory->find($this->input('municipality_id'));

        if ($chosen !== null && PortalText::normalize($chosen->inputLabel()) === PortalText::normalize($text)) {
            return $chosen;
        }

        return $directory->fromText($text);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone', 'email' => 'e-mail', 'description' => 'descrição', 'consent' => 'autorização de contato', 'municipality' => 'município'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['consent.accepted' => 'Autorize o contato para enviar a solicitação.', 'municipality.required' => 'Informe o município do atendimento.'];
    }
}
