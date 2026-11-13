<?php

namespace App\Http\Requests\Tenant\Assistant;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentifyAssistantConversationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('tenant_id', $context->tenantId())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do cliente.',
            'phone.required' => 'Informe o telefone do cliente com DDD.',
            'service_id.exists' => 'O serviço escolhido não existe nesta empresa.',
        ];
    }
}
