<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Shared\Exceptions\BusinessRuleException;

class ProductService
{
    /**
     * Placeholder image used when a product is created without one.
     */
    public function defaultImageUrl(int $productId): string
    {
        return sprintf('https://picsum.photos/seed/product-%d/600/600', $productId);
    }

    /**
     * Deleting a product that appears in past orders would break order history.
     *
     * @throws BusinessRuleException
     */
    public function ensureCanBeDeleted(bool $hasOrderItems): void
    {
        if ($hasOrderItems) {
            throw new BusinessRuleException('Este produto possui pedidos e não pode ser excluído. Desative-o em vez disso.');
        }
    }
}
