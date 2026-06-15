<?php

namespace App\Support;

class LoginRoleCompatibility
{
    /**
     * Unified marketplace login: user and vendor portals accept either account type.
     * Admin remains isolated to the admin login URL.
     */
    public static function matches(?string $requestedRole, string $accountRole): bool
    {
        if ($requestedRole === null || $requestedRole === '') {
            return true;
        }

        if ($accountRole === 'admin' || $requestedRole === 'admin') {
            return $accountRole === $requestedRole;
        }

        $marketplaceRoles = ['user', 'vendor'];

        if (
            in_array($requestedRole, $marketplaceRoles, true)
            && in_array($accountRole, $marketplaceRoles, true)
        ) {
            return true;
        }

        return $accountRole === $requestedRole;
    }
}
