<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use Carbon\CarbonImmutable;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Ordering\Core\ValueObjects\DeliveryAddress;
use Modules\Ordering\Core\ValueObjects\OrderForPayment;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;
use Modules\Shared\Enums\ApiErrorCode;

/**
 * A contract that is never implemented: the ModuleBoundariesTest walks its signatures to prove
 * which native types make a class part of a module's public vocabulary.
 */
interface SignatureProbeContract
{
    public function pay(OrderForPayment $order): ?CustomerProfile;

    public function register(CreateUserDTO $data): void;

    public function quote(ShippingQuote|DeliveryAddress $target): int;

    public function at(CarbonImmutable $when, ApiErrorCode $code): static;
}
