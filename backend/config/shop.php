<?php

return [
    // Delay (in seconds) before the fake delivery job marks an order as delivered.
    'delivery_delay_seconds' => (int) env('ORDER_DELIVERY_DELAY_SECONDS', 10),

    'products_per_page' => 12,

    // Shipping per destination state: one fixed rate for the "Padrão" delivery, changed with a deploy.
    // price_cents is what the customer pays; business_days counts from the payment approval.
    'shipping_rates' => [
        'SP' => ['price_cents' => 1500, 'business_days' => 2],
        'RJ' => ['price_cents' => 2200, 'business_days' => 4],
        'MG' => ['price_cents' => 2200, 'business_days' => 4],
        'ES' => ['price_cents' => 2200, 'business_days' => 4],
        'PR' => ['price_cents' => 2500, 'business_days' => 5],
        'SC' => ['price_cents' => 2500, 'business_days' => 5],
        'RS' => ['price_cents' => 2500, 'business_days' => 5],
        'DF' => ['price_cents' => 3000, 'business_days' => 6],
        'GO' => ['price_cents' => 3000, 'business_days' => 6],
        'MT' => ['price_cents' => 3000, 'business_days' => 6],
        'MS' => ['price_cents' => 3000, 'business_days' => 6],
        'BA' => ['price_cents' => 3800, 'business_days' => 8],
        'SE' => ['price_cents' => 3800, 'business_days' => 8],
        'AL' => ['price_cents' => 3800, 'business_days' => 8],
        'PE' => ['price_cents' => 3800, 'business_days' => 8],
        'PB' => ['price_cents' => 3800, 'business_days' => 8],
        'RN' => ['price_cents' => 3800, 'business_days' => 8],
        'CE' => ['price_cents' => 3800, 'business_days' => 8],
        'PI' => ['price_cents' => 3800, 'business_days' => 8],
        'MA' => ['price_cents' => 3800, 'business_days' => 8],
        'PA' => ['price_cents' => 4500, 'business_days' => 10],
        'AP' => ['price_cents' => 4500, 'business_days' => 10],
        'AM' => ['price_cents' => 4500, 'business_days' => 10],
        'RR' => ['price_cents' => 4500, 'business_days' => 10],
        'AC' => ['price_cents' => 4500, 'business_days' => 10],
        'RO' => ['price_cents' => 4500, 'business_days' => 10],
        'TO' => ['price_cents' => 4500, 'business_days' => 10],
    ],
];
