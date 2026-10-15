<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Gateways\FakePaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the payment gateway port to its adapter. This is the only place that names the adapter.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PaymentGateway::class => FakePaymentGateway::class,
    ];
}
