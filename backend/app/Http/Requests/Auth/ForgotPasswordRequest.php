<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\GuardsAccountInput;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            // Deliberately no "exists" rule: that would reveal registered emails.
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
