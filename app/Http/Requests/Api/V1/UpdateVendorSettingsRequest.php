<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateVendorSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, File|string|ValidationRule>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'logo' => ['sometimes', 'nullable', File::image()->max(10 * 1024)],
            'settings' => ['sometimes', 'array'],
            'settings.notifications' => ['sometimes', 'array'],
            'settings.notifications.email' => ['sometimes', 'boolean'],
            'settings.notifications.sms' => ['sometimes', 'boolean'],
            'settings.notifications.whatsapp' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notifications = $this->input('settings.notifications');
        if (! is_array($notifications)) {
            return;
        }

        $normalized = [];
        foreach (['email', 'sms', 'whatsapp'] as $key) {
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
}
