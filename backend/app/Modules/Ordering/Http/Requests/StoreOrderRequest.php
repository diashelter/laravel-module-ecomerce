<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests;

use App\Modules\Ordering\Models\Order;

class StoreOrderRequest extends ValidateCartRequest
{
    public function authorize(): bool
    {
        return $this->user('customer')->can('create', Order::class);
    }
}
