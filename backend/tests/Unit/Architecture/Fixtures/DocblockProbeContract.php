<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ValidatedCart;
use Illuminate\Support\Collection;

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
