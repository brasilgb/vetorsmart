<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SupportTicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A company opening a support request. The company comes from the session context, never from the payload, and the
 * priority is set by the platform team.
 */
class StoreSupportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'category' => ['required', Rule::enum(SupportTicketCategory::class)],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Informe o assunto.',
            'subject.min' => 'O assunto precisa de ao menos 5 caracteres.',
            'category.*' => 'Escolha uma categoria.',
            'description.required' => 'Descreva a situação.',
            'description.min' => 'A descrição precisa de ao menos 10 caracteres.',
        ];
    }
}
