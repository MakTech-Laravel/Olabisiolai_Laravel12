<?php

return [
    'currency' => 'NGN',

    'packages' => [
        [
            'id' => 'business',
            'title' => 'Business Name',
            'amount' => 5000,
            'description' => 'For registered and unregistered sole proprietors. Includes identity verification, proof of address and optional CAC.',
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
