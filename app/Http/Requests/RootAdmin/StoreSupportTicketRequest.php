<?php

namespace App\Http\Requests\RootAdmin;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opening a support record: subject, description, priority and, optionally, the related company.
 */
class StoreSupportTicketRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['required', Rule::enum(SupportTicketPriority::class)],
            'category' => ['nullable', Rule::enum(SupportTicketCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'Empresa não encontrada.',
            'subject.required' => 'Informe o assunto.',
            'subject.min' => 'O assunto precisa de ao menos 5 caracteres.',
            'description.required' => 'Descreva a situação.',
            'description.min' => 'A descrição precisa de ao menos 10 caracteres.',
            'priority.*' => 'Escolha uma prioridade válida.',
            'category.*' => 'Escolha uma categoria válida.',
        ];
    }
}
