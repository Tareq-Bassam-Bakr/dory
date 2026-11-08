<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The guest device to remove, by the public id returned at registration.
 * The ticket token itself travels in the X-Ticket-Token header.
 */
class UnregisterGuestDeviceRequest extends FormRequest
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
            'device' => ['required', 'string', 'size:26', 'alpha_num'],
        ];
    }
}
