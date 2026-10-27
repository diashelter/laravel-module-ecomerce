<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Repositories;

use Modules\Catalog\Persistence\ReadModel\ConditionalUpsert;
use Modules\Catalog\Persistence\ReadModel\ReadModelStore;

/**
 * The writes of catalog_products. Each is one conditional upsert, so the stored version decides in the database, not
 * in PHP, and two deliveries that run at the same time end with the higher version (AD-005):
 *
 * - a product part is replaced whole only when its version is greater than the stored product part and than
 *   `deleted_version`;
 * - a stock part is replaced whole only when its version is greater than the stored stock part;
 * - a tombstone sets `deleted_version` only when it is greater than the stored one.
 *
 * A write that finds a newer version matches no document: ConditionalUpsert turns the duplicate key of that case into
 * a discard (and a lost insert race into a second try). Every method returns whether it wrote.
 */
class ProductDocumentRepository
{
    public function __construct(private readonly ReadModelStore $store) {}

    /** @param  array<string, mixed>  $product  the whole product part, with its `version` */
    public function replaceProductPart(string $id, array $product): bool
    {
        $version = $product['version'];

        return $this->write(
            ['_id' => $id, '$and' => [
                ['$or' => [['product' => null], ['product.version' => ['$lt' => $version]]]],
                ['$or' => [['deleted_version' => null], ['deleted_version' => ['$lt' => $version]]]],
            ]],
            ['$set' => ['product' => $product], '$setOnInsert' => ['stock' => null, 'deleted_version' => null]],
        );
    }

    /** @param  array<string, mixed>  $stock  the whole stock part: `id`, `quantity` and `version` */
    public function replaceStockPart(string $productId, array $stock): bool
    {
        return $this->write(
            ['_id' => $productId, '$or' => [['stock' => null], ['stock.version' => ['$lt' => $stock['version']]]]],
            ['$set' => ['stock' => $stock], '$setOnInsert' => ['product' => null, 'deleted_version' => null]],
        );
    }

    public function tombstone(string $id, int $version): bool
    {
        return $this->write(
            ['_id' => $id, '$or' => [['deleted_version' => null], ['deleted_version' => ['$lt' => $version]]]],
            ['$set' => ['deleted_version' => $version], '$setOnInsert' => ['product' => null, 'stock' => null]],
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $update
     */
    private function write(array $filter, array $update): bool
    {
        return ConditionalUpsert::apply($this->store->products(), $filter, $update);
    }
}
