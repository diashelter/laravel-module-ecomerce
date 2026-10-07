<?php

declare(strict_types=1);

namespace App\Modules\Customers\DTOs;

use App\Modules\Ordering\Enums\BrazilianState;

/**
 * The address fields the customer types. The postal code is already the 8 digits (no hyphen).
 */
final readonly class CustomerAddressDTO
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

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'postal_code' => $this->postalCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state->value,
        ];
    }
}
