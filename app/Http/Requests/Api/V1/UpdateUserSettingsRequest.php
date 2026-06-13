<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateUserSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('wants_marketing_emails')) {
            $parsed = filter_var(
                $this->input('wants_marketing_emails'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
            if ($parsed !== null) {
                $this->merge(['wants_marketing_emails' => $parsed]);
            }
        }

        $notifications = $this->input('settings.notifications');
        if (! is_array($notifications)) {
            return;
        }

        $normalized = [];
        foreach (['email', 'push', 'sms', 'whatsapp'] as $key) {
            if (! array_key_exists($key, $notifications)) {
                continue;
            }
            $value = $notifications[$key];
            if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
                $normalized[$key] = true;
            } elseif ($value === '0' || $value === 0 || $value === false || $value === 'false') {
                $normalized[$key] = false;
            }
        }

        if ($normalized !== []) {
            $this->merge([
                'settings' => array_replace_recursive(
                    is_array($this->input('settings')) ? $this->input('settings') : [],
                    ['notifications' => array_replace($notifications, $normalized)],
                ),
            ]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->user('api');

        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user?->id),
            ],
            'wants_marketing_emails' => ['sometimes', 'boolean'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', File::image()->max(10 * 1024)],
            'settings' => ['sometimes', 'array'],
            'settings.notifications' => ['sometimes', 'array'],
            'settings.notifications.email' => ['sometimes', 'boolean'],
            'settings.notifications.push' => ['sometimes', 'boolean'],
            'settings.notifications.sms' => ['sometimes', 'boolean'],
            'settings.notifications.whatsapp' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $settings = $this->input('settings');
            if (! is_array($settings)) {
                return;
            }

            try {
                $encoded = json_encode($settings, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $validator->errors()->add('settings', 'Settings must be JSON-serializable.');

                return;
            }
            if (strlen($encoded) > 32768) {
                $validator->errors()->add('settings', 'Settings payload is too large (max 32KB).');
            }
        });
    }
}
