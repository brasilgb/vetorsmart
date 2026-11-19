<?php

namespace App\Http\Requests\RootAdmin;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A follow-up: a note, a status change, a priority change, or any combination. At least one is required.
 */
class UpdateSupportTicketRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000', 'required_without_all:status,priority'],
            'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
            'priority' => ['nullable', Rule::enum(SupportTicketPriority::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_without_all' => 'Escreva o acompanhamento ou altere a situação ou a prioridade.',
            'status.*' => 'Escolha uma situação válida.',
            'priority.*' => 'Escolha uma prioridade válida.',
        ];
    }
}
