<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\ProductCategories;
use Modules\Catalog\Core\ValueObjects\ProductCategory;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Core\ValueObjects\ProductView;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Catalog\Persistence\ReadModel\ReadModelStore;
use Modules\Inventory\Core\ValueObjects\ProductStock;
use Modules\Shared\Exceptions\ServiceUnavailableException;
use MongoDB\Driver\Exception\RuntimeException as MongoDbException;

/**
 * The reads of the storefront (GET /api/products and GET /api/products/{product}): they read the read model in
 * MongoDB and nothing else, never PostgreSQL and never the inventory. A page costs a fixed number of reads, whatever
 * its size: the count, the page of documents and the categories of the whole page (one more read to resolve the
 * category slug of a filter). The query holds no rule: the availability is decided by the ProductAvailability of the
 * ProductView the caller gets, so the documents hold only what the events carried and the filter never looks at the
 * status or at the quantity (a product that cannot be bought is listed, greyed out).
 *
 * MongoDB that does not answer is a 503 (ServiceUnavailableException), never a partial page.
 */
class StorefrontQuery
{
    private const TYPE_MAP = ['root' => 'array', 'document' => 'array', 'array' => 'array'];

    public function __construct(private readonly ReadModelStore $store) {}

    /**
     * Every document with a product part and no tombstone, filtered, sorted and paginated in MongoDB.
     *
     * @return LengthAwarePaginator<int, ProductView>
     *
     * @throws ServiceUnavailableException
     */
    public function paginate(?string $categorySlug, string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->guarded(function () use ($categorySlug, $sort, $perPage): LengthAwarePaginator {
            $page = Paginator::resolveCurrentPage();
            $filter = ['product' => ['$ne' => null], 'deleted_version' => null];

            if ($categorySlug !== null) {
                $categoryIds = $this->liveCategoryIdsOf($categorySlug);

                if ($categoryIds === []) {
                    return $this->paginatorOf([], 0, $perPage, $page);
                }

                $filter['product.category_ids'] = ['$in' => $categoryIds];
            }

            $total = $this->store->products()->countDocuments($filter);
            $documents = $this->store->products()->find($filter, [
                'sort' => match ($sort) {
                    'price_asc' => ['product.price_cents' => 1, '_id' => 1],
                    'price_desc' => ['product.price_cents' => -1, '_id' => 1],
                    default => ['product.name' => 1, '_id' => 1],
                },
                'skip' => ($page - 1) * $perPage,
                'limit' => $perPage,
                'typeMap' => self::TYPE_MAP,
            ])->toArray();

            $categories = $this->categoriesOf($documents);

            return $this->paginatorOf(array_map(fn (array $document) => $this->viewOf($document, $categories), $documents), $total, $perPage, $page);
        });
    }

    /**
     * The listed document of the product: a product part and no tombstone.
     *
     * @throws ModelNotFoundException when no such document exists
     * @throws ServiceUnavailableException
     */
    public function find(string $id): ProductView
    {
        return $this->guarded(function () use ($id): ProductView {
            $document = $this->store->products()->findOne(
                ['_id' => $id, 'product' => ['$ne' => null], 'deleted_version' => null],
                ['typeMap' => self::TYPE_MAP],
            );

            if ($document === null) {
                throw (new ModelNotFoundException)->setModel(Product::class, [$id]);
            }

            return $this->viewOf($document, $this->categoriesOf([$document]));
        });
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $read
     * @return TResult
     */
    private function guarded(callable $read): mixed
    {
        try {
            return $read();
        } catch (MongoDbException) {
            throw new ServiceUnavailableException;
        }
    }

    /** @return list<string> the ids of the categories with the slug that are not tombstones */
    private function liveCategoryIdsOf(string $slug): array
    {
        return array_column(
            $this->store->categories()->find(['slug' => $slug, 'deleted_version' => null], ['projection' => ['_id' => 1], 'typeMap' => self::TYPE_MAP])->toArray(),
            '_id',
        );
    }

    /**
     * The live categories the documents cite, by id, in one read.
     *
     * @param  list<array<string, mixed>>  $documents
     * @return array<string, ProductCategory>
     */
    private function categoriesOf(array $documents): array
    {
        $ids = array_values(array_unique(array_merge(...array_map(fn (array $document) => $document['product']['category_ids'] ?? [], $documents) ?: [[]])));

        if ($ids === []) {
            return [];
        }

        $categories = [];

        foreach ($this->store->categories()->find(['_id' => ['$in' => $ids], 'deleted_version' => null], ['typeMap' => self::TYPE_MAP]) as $category) {
            $categories[$category['_id']] = new ProductCategory($category['_id'], $category['name'], $category['slug']);
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, ProductCategory>  $categories
     */
    private function viewOf(array $document, array $categories): ProductView
    {
        $product = $document['product'];
        $stock = $document['stock'] ?? null;

        return new ProductView(
            new ProductRecord(
                $document['_id'],
                $product['name'],
                $product['price_cents'],
                (string) $product['description'],
                $product['image_url'],
                ProductStatus::from($product['status']),
                CarbonImmutable::parse($product['created_at']),
                $product['updated_at'] === null ? null : CarbonImmutable::parse($product['updated_at']),
                $product['version'],
            ),
            // The order of category_ids, without the category that has not arrived or was deleted.
            new ProductCategories(...array_values(array_filter(array_map(fn (string $id) => $categories[$id] ?? null, $product['category_ids'])))),
            $stock === null ? null : new ProductStock($stock['id'], $stock['quantity']),
        );
    }

    /**
     * @param  list<ProductView>  $views
     * @return LengthAwarePaginator<int, ProductView>
     */
    private function paginatorOf(array $views, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new Paginator($views, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
