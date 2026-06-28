<?php

return [
    'currency' => 'NGN',

    /** Dynamic visibility boost (no slot tiers). */
    'dynamic' => [
        'tier_key' => 'dynamic',
        'tier_label' => 'Dynamic Boost',
        'durations' => [1, 3, 7, 14, 30],
        'budget_min' => 500,
        'budget_max' => 5000,
        /** Fair rotation window in seconds for tied boosted listings. */
        'rotation_window_seconds' => 300,
    ],

    /**
     * Legacy slot-tier boosts (top_1 / top_5 / top_10) remain supported for
     * existing campaigns until they expire.
     */
];
