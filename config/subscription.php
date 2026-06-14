<?php

return [
    'currency' => 'NGN',
    'duration_days' => 365,

    'photo_limits' => [
        'free' => 5,
        'premium' => 20,
    ],

    'packages' => [
        [
            'id' => 'premium_yearly',
            'title' => 'Premium',
            'amount' => 25000,
            'description' => 'Annual premium subscription with full vendor features and marketplace visibility.',
            'perks' => [
                'Up to 20 photos',
                'Full analytics dashboard',
                'Priority boost access',
                'Featured in search results',
            ],
        ],
    ],
];
