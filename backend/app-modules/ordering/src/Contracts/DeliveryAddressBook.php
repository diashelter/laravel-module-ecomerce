<?php

declare(strict_types=1);

namespace Modules\Ordering\Contracts;

use Modules\Ordering\Core\ValueObjects\DeliveryAddress;

/**
 * What the ordering side needs from the customer's address book, without depending on the
 * customers module: the copy of one address, only if it belongs to that customer.
 *
 * Defined here, by the ordering module, and implemented by the customers module (dependency
 * inversion, like the catalog's ProductOrderHistory).
 */
interface DeliveryAddressBook
{
    /** Null when the address does not exist or belongs to another customer. */
    public function find(string $customerId, string $addressId): ?DeliveryAddress;
}
