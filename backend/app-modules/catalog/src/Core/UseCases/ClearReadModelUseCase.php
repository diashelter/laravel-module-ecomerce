<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Modules\Catalog\Persistence\ReadModel\ReadModelStore;

/** Rebuild of the storefront, first step: empties both collections and leaves them with the indexes of door 4. */
final class ClearReadModelUseCase
{
    public function __construct(private readonly ReadModelStore $store) {}

    public function execute(): void
    {
        $this->store->clear();
    }
}
