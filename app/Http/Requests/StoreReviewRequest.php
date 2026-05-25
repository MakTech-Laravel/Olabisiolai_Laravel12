<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api') !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_anonymous' => false,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_id' => 'required|integer|exists:business_info,id',
            'full_name' => 'nullable|string|max:255',
            'is_anonymous' => 'boolean',
            'rating' => 'required|integer|min:1|max:5',
            'review_text' => 'required|string|min:10|max:2000',
            'images' => 'nullable|array|max:5',
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Please provide a rating.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot be more than 5 stars.',
            'review_text.required' => 'Please write a review.',
            'review_text.min' => 'Review must be at least 10 characters long.',
            'review_text.max' => 'Review cannot exceed 2000 characters.',
            'images.max' => 'You can upload a maximum of 5 images.',
            'images.*.image' => 'All files must be images.',
            'images.*.mimes' => 'Images must be in JPG, PNG, or WebP format.',
            'images.*.max' => 'Each image cannot be larger than 5MB.',
            'auth_required' => 'You must be logged in to submit a review.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->user('api')) {
                $validator->errors()->add('auth_required', 'You must be logged in to submit a review.');
            }

            if ($this->boolean('is_anonymous')) {
                $validator->errors()->add('is_anonymous', 'Anonymous reviews are not allowed.');
            }
        });
    }
}
