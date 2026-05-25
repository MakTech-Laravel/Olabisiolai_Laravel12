<?php

namespace Database\Factories;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\BusinessInfo;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_info_id' => BusinessInfo::factory(),
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 2500,
            'currency' => 'NGN',
            'tx_ref' => 'verification_' . Str::lower(Str::random(16)),
            'status' => PaymentStatus::Pending,
            'is_consumed' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::Completed,
            'gateway_transaction_id' => (string) fake()->randomNumber(8),
            'paid_at' => now(),
        ]);
    }
}
