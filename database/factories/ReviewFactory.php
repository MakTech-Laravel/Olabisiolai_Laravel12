<?php

namespace Database\Factories;

use App\Models\BusinessInfo;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'business_id' => null,
            'full_name' => fake()->name(),
            'is_anonymous' => false,
            'rating' => fake()->numberBetween(1, 5),
            'review_text' => fake()->paragraph(),
            'is_approved' => true,
            'flag_reason' => null,
            'flagged_at' => null,
        ];
    }

    /**
     * Associate the review with a user.
     */
    public function forUser(User|int $user): static
    {
        return $this->state(fn(array $_attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    /**
     * Associate the review with a business.
     */
    public function forBusiness(BusinessInfo|int $business): static
    {
        return $this->state(fn(array $_attributes) => [
            'business_id' => $business instanceof BusinessInfo ? $business->id : $business,
        ]);
    }

    /**
     * Mark the review as anonymous (user_id is null regardless of auth).
     */
    public function anonymous(): static
    {
        return $this->state(fn(array $_attributes) => [
            'is_anonymous' => true,
            'user_id' => null,
        ]);
    }

    /**
     * Mark the review as flagged with an optional reason.
     */
    public function flagged(?string $reason = null): static
    {
        return $this->state(fn(array $attributes) => [
            'is_approved' => false,
            'flag_reason' => $reason ?? fake()->sentence(),
            'flagged_at' => now(),
        ]);
    }

    /**
     * Mark the review as not approved (pending moderation).
     */
    public function notApproved(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_approved' => false,
            'flagged_at' => null,
        ]);
    }
}
