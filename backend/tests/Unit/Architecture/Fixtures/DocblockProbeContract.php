<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use Illuminate\Support\Collection;
use Modules\Ordering\Core\ValueObjects\OrderLines;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;

/**
 * A contract that names module types only in docblocks: PHP does not enforce them, so they never
 * make a class part of a module's public vocabulary.
 */
interface DocblockProbeContract
{
    /** @return list<ValidatedCart> */
    public function carts(): array;

    /** @param Collection<int, OrderLines> $lines */
    public function keep(Collection $lines): void;
}
