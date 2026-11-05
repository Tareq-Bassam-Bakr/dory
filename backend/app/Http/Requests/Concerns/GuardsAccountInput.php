<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared input rules for account-related requests.
 */
trait GuardsAccountInput
{
    /**
     * Account fields a client may never set. Sending any of them fails
     * validation (422) instead of being silently ignored, which makes
     * privilege-escalation attempts explicit and testable.
     *
     * @return array<string, list<string>>
     */
    protected function privilegedFieldRules(): array
    {
        return [
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
            'is_platform_admin' => ['prohibited'],
            'status' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'last_login_at' => ['prohibited'],
        ];
    }

    /**
     * Lower-cases and trims the email, and strips spaces, dashes, dots and
     * parentheses from the phone number before validation.
     */
    protected function normalizeAccountInput(): void
    {
        $normalized = [];

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('phone'))) {
            $phone = preg_replace('/[\s\-.()]/', '', $this->input('phone'));
            $normalized['phone'] = $phone === '' ? null : $phone;
        }

        $this->merge($normalized);
    }

    /**
     * E.164: a plus sign followed by 7 to 15 digits, the first not zero.
     */
    protected function phoneFormatRule(): string
    {
        return 'regex:/^\+[1-9]\d{6,14}$/';
    }
}
