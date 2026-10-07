<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests;

/**
 * Same payload as the cart validation. Who may place an order is decided by the `auth:customer`
 * route group: every shopper account can, and staff have no store session.
 */
class StoreOrderRequest extends ValidateCartRequest {}
