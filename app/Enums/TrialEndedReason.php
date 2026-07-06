<?php

namespace App\Enums;

enum TrialEndedReason: string
{
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case UpgradedToPaid = 'upgraded_to_paid';
}
