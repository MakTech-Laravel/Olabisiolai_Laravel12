<?php

namespace Database\Factories;

use App\Enums\VerificationDocumentStatus;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationDocument>
 */
class VerificationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_info_id' => BusinessInfo::factory(),
            'uploaded_by' => User::factory(),
            'document_type' => fake()->randomElement(['payment_receipt', 'bank_transfer', 'business_registration', 'cac_document', 'other']),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'file_path' => 'businesses/sample/verification/receipt.pdf',
            'file_name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'status' => VerificationDocumentStatus::Pending,
            'rejection_reason' => null,
            'expires_at' => null,
        ];
    }
}
