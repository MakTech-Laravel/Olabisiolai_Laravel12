<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $admin = $this->user('admin_api') ?? $this->user('admin');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'nullable', 'confirmed', 'min:8'],
        ];
    }
}
