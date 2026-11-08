<?php

namespace App\Http\Requests\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Any subset of the preference flags; the others keep their value.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
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
        return array_fill_keys(NotificationPreference::FLAGS, ['sometimes', 'boolean']);
    }

    /**
     * @return array<string, bool>
     */
    public function flags(): array
    {
        return array_map(fn ($value): bool => (bool) $value, $this->safe()->only(NotificationPreference::FLAGS));
    }
}
