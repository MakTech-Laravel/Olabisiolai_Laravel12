<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLoginRequest extends FormRequest
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
        return [
            'role' => ['required', Rule::in(['user', 'vendor'])],
            'access_token' => ['nullable', 'string', 'required_without_all:id_token,code'],
            'id_token' => ['nullable', 'string', 'required_without_all:access_token,code'],
            'code' => ['nullable', 'string', 'required_without_all:access_token,id_token'],
            'redirect_uri' => ['nullable', 'url', 'required_with:code'],
        ];
    }
}
