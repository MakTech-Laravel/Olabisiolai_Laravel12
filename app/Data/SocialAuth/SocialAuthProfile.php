<?php

namespace App\Data\SocialAuth;

readonly class SocialAuthProfile
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public ?string $name,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $avatarUrl,
        public bool $emailVerified = false,
        public array $meta = [],
    ) {}

    public function resolvedFirstName(): string
    {
        if (filled($this->firstName)) {
            return (string) $this->firstName;
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return $parts[0] ?? 'User';
    }

    public function resolvedLastName(): string
    {
        if (filled($this->lastName)) {
            return (string) $this->lastName;
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        array_shift($parts);

        return trim(implode(' ', $parts)) ?: 'Account';
    }

    public function resolvedName(): string
    {
        return trim(((string) $this->name) ?: trim($this->resolvedFirstName().' '.$this->resolvedLastName()));
    }
}
