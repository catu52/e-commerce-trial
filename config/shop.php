<?php

return [
    'currency' => 'EUR',

    // Public storefront origin (SPA), used to build SEO URLs.
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    // Products at or below this stock level are flagged in the CMS panel.
    'low_stock_threshold' => 5,

    'delivery_costs' => [
        'standard' => 0,
        'express' => 15,
        'sameday' => 25,
    ],

    // Percentage discounts applied to the subtotal.
    'promo_codes' => [
        'BAUHAUS10' => 10,
        'KRAFT' => 10,
    ],
];
