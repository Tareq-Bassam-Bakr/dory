<?php

namespace App\Http\Requests\Queues;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /tickets/{ticket}/review: a rating from 1 to 5, nothing else.
 */
class ReviewTicketRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'user_id' => ['prohibited'],
            'business_id' => ['prohibited'],
        ];
    }
}
