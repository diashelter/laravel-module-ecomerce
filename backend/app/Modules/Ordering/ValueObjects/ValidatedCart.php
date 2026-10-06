<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The result of validating a cart. Only the lines without a problem count towards the total,
 * and the cart is valid only when it has lines and none of them has a problem.
 *
 * @implements IteratorAggregate<int, ValidatedCartLine>
 */
final readonly class ValidatedCart implements Countable, IteratorAggregate
{
    /** @var list<ValidatedCartLine> */
    private array $lines;

    public function __construct(ValidatedCartLine ...$lines)
    {
        $this->lines = array_values($lines);
    }

    public function totalCents(): int
    {
        return array_sum(array_map(
            fn (ValidatedCartLine $line) => $line->hasProblem() ? 0 : $line->subtotalCents(),
            $this->lines,
        ));
    }

    public function isValid(): bool
    {
        return $this->lines !== [] && array_all($this->lines, fn (ValidatedCartLine $line) => ! $line->hasProblem());
    }

    public function count(): int
    {
        return count($this->lines);
    }

    /** @return Traversable<int, ValidatedCartLine> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->lines);
    }
}
