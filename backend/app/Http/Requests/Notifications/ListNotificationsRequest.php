<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'string', Rule::in(['all', 'unread', 'read'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function filter(): string
    {
        return (string) ($this->validated('filter') ?? 'all');
    }
}
