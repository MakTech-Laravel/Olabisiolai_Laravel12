<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\BillingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_key' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:120'],
            'billing_period' => ['required', 'string', Rule::in(BillingPeriod::values())],
            'amount' => ['required', 'integer', 'min:1'],
            'original_price' => ['nullable', 'integer', 'gt:amount'],
            'promotional_text' => ['nullable', 'string', 'max:255'],
            'promotion_starts_at' => ['nullable', 'date'],
            'promotion_ends_at' => ['nullable', 'date', 'after:promotion_starts_at'],
            'description' => ['nullable', 'string', 'max:2000'],
            'perks' => ['nullable', 'array'],
            'perks.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_recommended' => ['sometimes', 'boolean'],
            'trial_eligible' => ['sometimes', 'boolean'],
            'trial_duration_days' => ['required_if:trial_eligible,true', 'nullable', 'integer', 'min:1', 'max:365'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
