<?php

namespace Database\Seeders;

use App\Models\BusinessInfo;
use Database\Seeders\Support\SocialAccountSeedCatalog;
use Illuminate\Database\Seeder;

class BusinessSocialAccountsSeeder extends Seeder
{
    /**
     * Populate demo social_accounts for businesses that have none.
     */
    public function run(): void
    {
        BusinessInfo::query()
            ->select(['id', 'business_name', 'social_accounts'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses): void {
                foreach ($businesses as $business) {
                    if ($this->hasSocialAccounts($business->social_accounts)) {
                        continue;
                    }

                    $business->forceFill([
                        'social_accounts' => SocialAccountSeedCatalog::forBusiness($business->business_name),
                    ])->saveQuietly();
                }
            });
    }

    private function hasSocialAccounts(mixed $accounts): bool
    {
        if (! is_array($accounts) || $accounts === []) {
            return false;
        }

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $url = trim((string) ($account['url'] ?? ''));
            if ($url !== '') {
                return true;
            }
        }

        return false;
    }
}
