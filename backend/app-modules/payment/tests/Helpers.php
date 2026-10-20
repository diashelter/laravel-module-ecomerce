<?php

use Modules\Payment\Contracts\OrdersToPay;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Core\ValueObjects\OrderToPay;
use Modules\Payment\Gateways\FakePaymentGateway;
use Modules\Shared\Exceptions\ServiceUnavailableException;

/**
 * Binds a PaymentGateway double that records every charge it receives. It answers like the
 * fake gateway unless $answer (which receives the ChargeRequest) decides the result.
 */
function spyPaymentGateway(?Closure $answer = null): object
{
    $spy = new class($answer) implements PaymentGateway
    {
        /** @var list<ChargeRequest> */
        public array $charges = [];

        public function __construct(private readonly ?Closure $answer) {}

        public function charge(ChargeRequest $request): ChargeResult
        {
            $this->charges[] = $request;

            return $this->answer !== null
                ? ($this->answer)($request)
                : (new FakePaymentGateway)->charge($request);
        }
    };

    app()->instance(PaymentGateway::class, $spy);

    return $spy;
}

/** An order as the order port answers it: awaiting payment unless said otherwise. */
function orderToPay(string $customerId, int $totalCents = 12345, bool $awaitingPayment = true): OrderToPay
{
    return new OrderToPay(unknownId(), $customerId, $totalCents, $awaitingPayment);
}

/**
 * Binds an order port double that answers each order only to its own customer (null otherwise, as the
 * lookup by owner does) and records every [customer id, order id] it is asked.
 */
function fakeOrdersToPay(OrderToPay ...$orders): object
{
    $port = new class($orders) implements OrdersToPay
    {
        /** @var list<array{string, string}> */
        public array $asked = [];

        /** @param  list<OrderToPay>  $orders */
        public function __construct(private readonly array $orders) {}

        public function findForPayment(string $customerId, string $orderId): ?OrderToPay
        {
            $this->asked[] = [$customerId, $orderId];

            foreach ($this->orders as $order) {
                if ($order->id === $orderId && $order->customerId === $customerId) {
                    return $order;
                }
            }

            return null;
        }
    };

    app()->instance(OrdersToPay::class, $port);

    return $port;
}

/** Binds an order port that is unavailable, as when no installed module serves it, and counts the calls. */
function unavailableOrdersToPay(): object
{
    $port = new class implements OrdersToPay
    {
        public int $calls = 0;

        public function findForPayment(string $customerId, string $orderId): ?OrderToPay
        {
            $this->calls++;

            throw new ServiceUnavailableException;
        }
    };

    app()->instance(OrdersToPay::class, $port);

    return $port;
}
