<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

/**
 * The slug is not part of the DTO: the Category model always derives it from the name.
 */
final readonly class CategoryDTO
{
    public function __construct(public string $name) {}

    /** @return array{name: string} */
    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
