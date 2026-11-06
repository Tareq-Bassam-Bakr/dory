<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /me/favorites: the public id of the business to save. The favorite
 * always belongs to the caller, so no user is accepted.
 */
class StoreFavoriteRequest extends FormRequest
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
            'business' => ['required', 'string', 'regex:/^[0-9A-Za-z]{26}$/'],
            'user_id' => ['prohibited'],
        ];
    }
}
