<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait AuthorizesMessagingApiUser
{
    protected function messagingApiUserAuthorized(): bool
    {
        if ($this->user('api') !== null) {
            return true;
        }

        return adminAuthCheck($this) !== null;
    }
}
