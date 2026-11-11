<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class SendAssistantMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:'.(int) config('assistant.max_visitor_message_chars', 500)],
            'after' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Digite sua mensagem.',
            'body.max' => 'Mensagem muito longa. Use até '.(int) config('assistant.max_visitor_message_chars', 500).' caracteres.',
        ];
    }
}
