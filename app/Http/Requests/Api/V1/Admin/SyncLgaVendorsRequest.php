<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncLgaVendorsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:locations,id'],
            'vendors' => ['required', 'array', 'min:1'],
            'vendors.*.vendor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'vendor')],
            'vendors.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'vendors.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
