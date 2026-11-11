<?php

namespace App\Http\Requests\Tenant\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssistantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'greeting' => ['nullable', 'string', 'max:500'],
            'presentation' => ['nullable', 'string', 'max:2000'],
            'service_region' => ['nullable', 'string', 'max:255'],
            'business_hours_text' => ['nullable', 'string', 'max:255'],
            'commercial_info' => ['nullable', 'string', 'max:2000'],
            'guidance' => ['nullable', 'string', 'max:2000'],
            'handoff_conditions' => ['nullable', 'string', 'max:1000'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Informe se a recepcionista virtual está ativa.',
            'retention_days.required' => 'Informe por quantos dias as conversas são mantidas.',
            'retention_days.min' => 'A retenção mínima é de 1 dia.',
            'retention_days.max' => 'A retenção máxima é de 365 dias.',
        ];
    }
}
