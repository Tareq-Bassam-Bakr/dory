<?php

namespace App\Http\Requests\Users;

use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::enum(AppLocale::class)],
        ];
    }

    public function locale(): AppLocale
    {
        return AppLocale::from($this->validated('locale'));
    }
}
