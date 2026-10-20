<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Catalog\Core\DTOs\UpdateProductStatusDTO;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Repositories\ProductRepository;

/**
 * Admin: activates or deactivates a product.
 */
final class ChangeProductStatusUseCase
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @throws ModelNotFoundException
     */
    public function execute(string $productId, UpdateProductStatusDTO $data): ProductRecord
    {
        $product = $this->products->findOrFail($productId);

        $this->products->update($product, ['status' => $data->status]);

        return $product->toRecord();
    }
}
