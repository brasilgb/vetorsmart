<?php

namespace App\Http\Requests\Tenant;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * E-mail preferences of the signed-in person in the active company. Only the optional categories can be sent; the
 * administrative notices are essential and have no switch. customer_emails is the company switch for the e-mails to
 * customers, accepted only from owners and admins (checked in the controller).
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['customer_emails' => ['sometimes', 'boolean']];

        foreach (NotificationCategory::optional() as $category) {
            $rules['email.'.$category->value] = ['sometimes', 'boolean'];
        }

        return [...$rules, 'email' => ['sometimes', 'array:'.implode(',', array_map(fn (NotificationCategory $category): string => $category->value, NotificationCategory::optional()))]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.array' => 'Preferência de e-mail inválida.',
        ];
    }
}
