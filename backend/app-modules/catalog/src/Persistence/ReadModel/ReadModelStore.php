<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\ReadModel;

use Illuminate\Support\Facades\DB;
use MongoDB\Collection;

/**
 * The read model of the storefront: the two MongoDB collections the catalog owns (door 4 of the storefront
 * read model) and their indexes. Only the projector consumers (through the document repositories) and
 * catalog:read-model-clear write into them; the storefront query only reads.
 */
class ReadModelStore
{
    public const CONNECTION = 'mongodb';

    public const PRODUCTS = 'catalog_products';

    public const CATEGORIES = 'catalog_categories';

    public function products(): Collection
    {
        return DB::connection(self::CONNECTION)->getCollection(self::PRODUCTS);
    }

    public function categories(): Collection
    {
        return DB::connection(self::CONNECTION)->getCollection(self::CATEGORIES);
    }

    /**
     * Empties both collections and leaves them with exactly the indexes of door 4. The collections are dropped, so
     * the tombstones go too.
     */
    public function clear(): void
    {
        $this->products()->drop();
        $this->categories()->drop();

        $this->ensureIndexes();
    }

    /**
     * Idempotent: an index that exists is left as it is. Only clear() calls it, so a MongoDB volume gets its indexes
     * from `make storefront-rebuild`; the projector alone would create the collections without them.
     */
    public function ensureIndexes(): void
    {
        $this->products()->createIndex(['product.category_ids' => 1]);
        $this->products()->createIndex(['product.name' => 1, '_id' => 1]);
        $this->products()->createIndex(['product.price_cents' => 1, '_id' => 1]);

        // Not unique: a category can reuse the slug of a tombstone.
        $this->categories()->createIndex(['slug' => 1]);
    }
}
