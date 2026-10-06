<?php

return [
    // Delay (in seconds) before the fake delivery job marks an order as delivered.
    'delivery_delay_seconds' => (int) env('ORDER_DELIVERY_DELAY_SECONDS', 10),

    'products_per_page' => 12,
];
