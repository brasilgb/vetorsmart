<?php

namespace App\Http\Requests\RootAdmin;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A follow-up by the platform team: a reply or internal note, a status or priority change, the person responsible or
 * the due date, in any combination. At least one is required.
 */
class UpdateSupportTicketRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000', 'required_without_all:status,priority,assigned_to,due_at'],
            'internal' => ['boolean'],
            'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
            'priority' => ['nullable', Rule::enum(SupportTicketPriority::class)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'due_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_without_all' => 'Escreva o acompanhamento ou altere a situação, a prioridade, o responsável ou o prazo.',
            'assigned_to.*' => 'Escolha um responsável válido.',
            'due_at.*' => 'Informe uma data e hora válidas para o prazo.',
            'status.*' => 'Escolha uma situação válida.',
            'priority.*' => 'Escolha uma prioridade válida.',
        ];
    }
}
