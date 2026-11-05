<?php

namespace App\Http\Requests\Auth;

use App\Enums\AppLocale;
use App\Http\Requests\Concerns\GuardsAccountInput;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', $this->phoneFormatRule(), Rule::unique(User::class, 'phone')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'locale' => ['sometimes', 'string', Rule::enum(AppLocale::class)],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'device_name' => ['required', 'string', 'max:100'],
            ...$this->privilegedFieldRules(),
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, phone?: ?string, locale?: string, timezone?: string}
     */
    public function userAttributes(): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'password', 'locale', 'timezone']);
    }

    public function deviceName(): string
    {
        return $this->validated('device_name');
    }
}
