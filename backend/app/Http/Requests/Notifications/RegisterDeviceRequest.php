<?php

namespace App\Http\Requests\Notifications;

use App\Enums\AppLocale;
use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A device token from Firebase Cloud Messaging. The token is validated for
 * shape only; FCM itself tells us when it is invalid (the device is then
 * revoked). `replaces` is the public id of the device row this token
 * replaces after a token refresh.
 */
class RegisterDeviceRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-\.]+$/'],
            'platform' => ['required', 'string', Rule::enum(DevicePlatform::class)],
            'locale' => ['sometimes', 'string', Rule::enum(AppLocale::class)],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'replaces' => ['sometimes', 'nullable', 'string', 'size:26', 'alpha_num'],
        ];
    }

    /**
     * @return array{token: string, platform: DevicePlatform, locale: string, app_version: ?string, replaces: ?string}
     */
    public function device(): array
    {
        return [
            'token' => (string) $this->validated('token'),
            'platform' => DevicePlatform::from((string) $this->validated('platform')),
            'locale' => (string) ($this->validated('locale') ?? AppLocale::English->value),
            'app_version' => $this->validated('app_version'),
            'replaces' => $this->validated('replaces'),
        ];
    }
}
