<?php

/**
 * Static location hierarchy for business listings (country → state → cities).
 * Keys are display values stored in business_info.location, .state, and .city.
 *
 * @var array<string, array<string, list<string>>>
 */
return [
    'Nigeria' => [
        'Lagos' => ['Ikeja', 'Surulere', 'Victoria Island', 'Yaba', 'Lekki'],
        'FCT' => ['Garki', 'Wuse', 'Maitama', 'Asokoro', 'Gwagwalada'],
        'Oyo' => ['Ibadan North', 'Ibadan South-East', 'Ibadan South-West'],
        'Rivers' => ['Port Harcourt', 'Obio-Akpor'],
        'Kano' => ['Kano Municipal', 'Nassarawa', 'Fagge'],
    ],
];
