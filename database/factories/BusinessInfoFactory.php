<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessSubscription;
use App\Services\BusinessHoursService;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessInfo>
 */
class BusinessInfoFactory extends Factory
{
    protected $model = BusinessInfo::class;

    public function configure(): static
    {
        return $this->afterCreating(function (BusinessInfo $business): void {
            app(BusinessHoursService::class)->seedDefaultsForBusiness($business);

            if ($business->subscription()->exists()) {
                return;
            }

            BusinessSubscription::factory()->create([
                'business_info_id' => $business->id,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'business_name' => fake()->company(),
            'business_description' => fake()->paragraph(),
            'services_offered' => [fake()->jobTitle(), fake()->jobTitle()],
            'phone' => fake()->numerify('+23480#######'),
            'whatsapp' => fake()->numerify('+23480#######'),
            'website' => fake()->url(),
            'logo_path' => 'businesses/sample/logo.png',
            'cover_photo_paths' => [
                'businesses/sample/covers/cover1.png',
                'businesses/sample/covers/cover2.png',
            ],
            'verification_status' => VerificationStatus::None,
            'is_flagged' => false,
            'business_status' => BusinessStatus::Active,
            'verified_by' => null,
            'verified_at' => null,
            'verification_note' => null,
        ];
    }

    public function premiumPending(): static
    {
        return $this->afterCreating(function (BusinessInfo $business): void {
            $business->subscription()->update([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::PendingPayment,
            ]);
            $business->update(['business_status' => BusinessStatus::Inactive]);
        });
    }

    public function premiumActive(): static
    {
        return $this->afterCreating(function (BusinessInfo $business): void {
            $business->subscription()->update([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::Active,
                'expires_at' => now()->addYear(),
            ]);
        });
    }
}
