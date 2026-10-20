<?php

use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Gateways\FakePaymentGateway;

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
