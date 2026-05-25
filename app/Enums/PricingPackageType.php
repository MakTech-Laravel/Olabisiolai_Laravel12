<?php

namespace App\Enums;

enum PricingPackageType: string
{
    case Verification = 'verification';
    case Subscription = 'subscription';
    /** Reference tiers; live boost checkout prices come from LGA boost config. */
    case Boost = 'boost';
}
