<?php

return [
    'tiers' => [40, 50, 60],

    'sorts' => ['discount', 'depletion', 'price_asc', 'price_desc'],

    'default_per_page' => 12,
    'max_per_page' => 48,

    'stock_state' => [
        'critical' => ['units' => 3, 'depletion' => 85],
        'low' => ['units' => 6, 'depletion' => 70],
    ],

    'newsletter_throttle' => '5,1',
];
