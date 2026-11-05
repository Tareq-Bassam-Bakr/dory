<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\GuardsAccountInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{email: string, token: string, password: string, password_confirmation: string}
     */
    public function credentials(): array
    {
        return [
            'email' => $this->validated('email'),
            'token' => $this->validated('token'),
            'password' => $this->validated('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
    }
}
