<?php

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;

/**
 * Port to whoever charges the customer. Bound in PaymentServiceProvider: swapping the gateway
 * is a new implementation and a new binding, with no change in the use case or the API.
 * The charge is synchronous; a gateway that confirms later would need a pending state.
 */
interface PaymentGateway
{
    public function charge(ChargeRequest $request): ChargeResult;
}
