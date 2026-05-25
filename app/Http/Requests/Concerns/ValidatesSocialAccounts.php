<?php

namespace App\Http\Requests\Concerns;

use App\Enums\SocialPlatform;
use Illuminate\Validation\Rule;

trait ValidatesSocialAccounts
{
    protected function prepareSocialAccountsFromRequest(): void
    {
        $accounts = $this->input('social_accounts');

        if (is_string($accounts)) {
            $decoded = json_decode($accounts, true);
            if (is_array($decoded)) {
                $this->merge(['social_accounts' => $decoded]);
            }

            return;
        }

        if (! is_array($accounts)) {
            return;
        }

        $normalized = [];

        foreach ($accounts as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized[] = [
                'platform' => strtolower(trim((string) ($entry['platform'] ?? ''))),
                'url' => trim((string) ($entry['url'] ?? '')),
            ];
        }

        $this->merge(['social_accounts' => $normalized]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function socialAccountsRules(): array
    {
        return [
            'social_accounts' => ['nullable', 'array', 'max:10'],
            'social_accounts.*.platform' => [
                'required',
                'string',
                Rule::in(SocialPlatform::values()),
            ],
            'social_accounts.*.url' => ['required', 'string', 'max:2048'],
        ];
    }
}
