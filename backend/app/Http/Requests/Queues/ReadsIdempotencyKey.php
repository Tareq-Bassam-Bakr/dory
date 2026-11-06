<?php

namespace App\Http\Requests\Queues;

use Illuminate\Validation\Validator;

/**
 * Optional Idempotency-Key header (16–64 URL-safe characters) that makes a
 * join safe to retry: the same key returns the ticket created the first time.
 */
trait ReadsIdempotencyKey
{
    protected function validateIdempotencyKey(Validator $validator): void
    {
        $key = $this->header('Idempotency-Key');

        if ($key !== null && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $key) !== 1) {
            $validator->errors()->add('idempotency_key', __('api.business.idempotency_key_invalid'));
        }
    }

    public function idempotencyKey(): ?string
    {
        return $this->header('Idempotency-Key');
    }
}
