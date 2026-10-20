<?php

declare(strict_types=1);

namespace Modules\Payment\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Gateways\FakePaymentGateway;

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
