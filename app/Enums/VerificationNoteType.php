<?php

namespace App\Enums;

enum VerificationNoteType: string
{
    case Internal = 'internal';
    case VendorCommunication = 'vendor_communication';
    case AdminDecision = 'admin_decision';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::VendorCommunication => 'Vendor Communication',
            self::AdminDecision => 'Admin Decision',
        };
    }
}
