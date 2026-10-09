<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use App\Modules\Ordering\ValueObjects\ValidatedCartLine;

/**
 * An event that is never dispatched: only its public property is part of its signature, so the
 * private property and the private method stay out of the vocabulary.
 */
final class SignatureProbeEvent
{
    public OrderStatus $status;

    private ValidatedCartLine $line;

    private function lines(OrderLines $lines): ProductQuantities
    {
        return new ProductQuantities;
    }
}
