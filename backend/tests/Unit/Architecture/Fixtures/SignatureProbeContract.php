<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Ordering\ValueObjects\ShippingQuote;
use App\Modules\Shared\Enums\ApiErrorCode;
use Carbon\CarbonImmutable;

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
