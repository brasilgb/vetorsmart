<?php

namespace App\Http\Requests\Tenant\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class OperatorReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Digite a mensagem para o cliente.',
            'body.max' => 'A mensagem pode ter até 2000 caracteres.',
        ];
    }
}
