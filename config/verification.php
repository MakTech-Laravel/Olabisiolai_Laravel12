<?php

return [
    'currency' => 'NGN',

    'packages' => [
        [
            'id' => 'individual',
            'title' => 'Individual',
            'amount' => 2500,
            'description' => 'Best for solo entrepreneurs and independent contractors. Requires government ID and personal biometric verification.',
            'perks' => ['Trusted badge'],
        ],
        [
            'id' => 'business',
            'title' => 'Business Name',
            'amount' => 5000,
            'description' => 'For registered sole proprietorships. Includes CAC document validation and business account linkage.',
            'perks' => ['Vendor priority', 'Storefront personalization'],
        ],
        [
            'id' => 'ltd',
            'title' => 'Limited Company (LTD)',
            'amount' => 10000,
            'description' => 'The gold standard for corporate entities. Comprehensive verification of directors, shareholders, and legal status.',
            'perks' => ['Enterprise blue badge'],
        ],
    ],
];
