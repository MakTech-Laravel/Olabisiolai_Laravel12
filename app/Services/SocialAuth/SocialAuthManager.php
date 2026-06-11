<?php

namespace App\Services\SocialAuth;

use App\Contracts\SocialAuth\SocialAuthProviderContract;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class SocialAuthManager
{
    /**
     * @return list<array{provider: string, label: string}>
     */
    public function enabledProviders(): array
    {
        $providers = [];

        foreach ($this->configuredProviders() as $name => $config) {
            $providers[] = [
                'provider' => $name,
                'label' => (string) ($config['label'] ?? ucfirst($name)),
            ];
        }

        return $providers;
    }

    public function isEnabled(string $provider): bool
    {
        return array_key_exists($provider, $this->configuredProviders());
    }

    public function driver(string $provider): SocialAuthProviderContract
    {
        $config = $this->configuredProviders()[$provider] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException("Social provider [{$provider}] is not enabled.");
        }

        $class = $config['driver'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Social provider [{$provider}] driver is invalid.");
        }

        $instance = app($class);

        if (! $instance instanceof SocialAuthProviderContract) {
            throw new InvalidArgumentException("Social provider [{$provider}] must implement SocialAuthProviderContract.");
        }

        return $instance;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredProviders(): array
    {
        $configured = config('social_auth.providers', []);

        return Arr::where(
            is_array($configured) ? $configured : [],
            fn (mixed $provider): bool => is_array($provider) && (bool) ($provider['enabled'] ?? false),
        );
    }
}
