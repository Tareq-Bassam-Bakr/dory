<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\Concerns\GuardsAccountInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Partial update of the authenticated user's own profile.
 *
 * Email changes are prohibited here: they will need a re-verification flow.
 * Language has its own endpoint (PUT /users/locale).
 */
class UpdateProfileRequest extends FormRequest
{
    use GuardsAccountInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAccountInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                $this->phoneFormatRule(),
                Rule::unique(User::class, 'phone')->ignore($this->user()),
            ],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            ...$this->privilegedFieldRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->hasAny(['name', 'phone', 'timezone'])) {
                    $validator->errors()->add('profile', __('api.users.profile_fields_required'));
                }
            },
        ];
    }
}
