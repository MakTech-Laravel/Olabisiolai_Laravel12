<?php

namespace App\Http\Requests;

use App\Enums\MessageReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(MessageReportReason::values())],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please select a reason for reporting this message.',
            'reason.in' => 'The selected report reason is invalid.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
