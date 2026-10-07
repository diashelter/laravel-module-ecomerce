<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use App\Modules\Ordering\Enums\BrazilianState;

/**
 * The copy of an address that an order keeps. It is a snapshot, not a reference: editing or
 * deleting the address in the customer's book never changes an order already placed.
 */
final readonly class DeliveryAddress
{
    public function __construct(
        public string $recipientName,
        public string $postalCode,
        public string $street,
        public string $number,
        public ?string $complement,
        public string $district,
        public string $city,
        public BrazilianState $state,
    ) {}
}
