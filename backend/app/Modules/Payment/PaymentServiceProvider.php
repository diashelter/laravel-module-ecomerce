<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Gateways\FakePaymentGateway;
use App\Modules\Payment\Policies\OrderPaymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the payment gateway port to its adapter. This is the only place that names the adapter.
 * Also resolves the order of the payment route through the ordering module's PayableOrders
 * contract (before the body is validated, so an unknown order is a 404, as with model binding),
 * and registers who may pay it: the order comes as data, so it cannot declare its policy itself.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PaymentGateway::class => FakePaymentGateway::class,
    ];

    public function boot(): void
    {
        Route::bind('payableOrder', fn (string $id): OrderForPayment => $this->app->make(PayableOrders::class)
            ->findForPayment((int) $id) ?? abort(404));

        Gate::policy(OrderForPayment::class, OrderPaymentPolicy::class);
    }
}
