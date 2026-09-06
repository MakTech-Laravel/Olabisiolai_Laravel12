<?php

namespace App\Http\Requests;

use App\Enums\ReviewReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessReportRequest extends FormRequest
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
            'reason' => [
                'required',
                Rule::in(array_map(
                    static fn (ReviewReportReason $reason) => $reason->value,
                    ReviewReportReason::forBusinessReports(),
                )),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please select a reason for reporting this business.',
            'reason.in' => 'The selected report reason is invalid.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
