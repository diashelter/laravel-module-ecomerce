<?php

use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Ordering\OrderingServiceProvider;
use App\Modules\Payment\PaymentServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    InventoryServiceProvider::class,
    OrderingServiceProvider::class,
    PaymentServiceProvider::class,
];
