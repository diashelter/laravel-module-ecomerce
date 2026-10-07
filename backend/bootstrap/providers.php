<?php

use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Fulfillment\FulfillmentServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Ordering\OrderingServiceProvider;
use App\Modules\Payment\PaymentServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CustomersServiceProvider::class,
    FulfillmentServiceProvider::class,
    InventoryServiceProvider::class,
    OrderingServiceProvider::class,
    PaymentServiceProvider::class,
];
