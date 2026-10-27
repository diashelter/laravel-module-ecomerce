<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Repositories;

use Modules\Catalog\Persistence\ReadModel\ConditionalUpsert;
use Modules\Catalog\Persistence\ReadModel\ReadModelStore;

/**
 * The writes of catalog_categories, by the same version rule as ProductDocumentRepository: a category is replaced
 * whole only when its version is greater than the stored one and than `deleted_version`, a tombstone only when it is
 * greater than the stored `deleted_version`, and a write that loses to a newer version is a discard.
 */
class CategoryDocumentRepository
{
    public function __construct(private readonly ReadModelStore $store) {}

    public function replace(string $id, string $name, string $slug, int $version): bool
    {
        return $this->write(
            ['_id' => $id, '$and' => [
                ['$or' => [['version' => null], ['version' => ['$lt' => $version]]]],
                ['$or' => [['deleted_version' => null], ['deleted_version' => ['$lt' => $version]]]],
            ]],
            ['$set' => ['name' => $name, 'slug' => $slug, 'version' => $version], '$setOnInsert' => ['deleted_version' => null]],
        );
    }

    public function tombstone(string $id, int $version): bool
    {
        return $this->write(
            ['_id' => $id, '$or' => [['deleted_version' => null], ['deleted_version' => ['$lt' => $version]]]],
            ['$set' => ['deleted_version' => $version], '$setOnInsert' => ['name' => null, 'slug' => null, 'version' => null]],
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $update
     */
    private function write(array $filter, array $update): bool
    {
        return ConditionalUpsert::apply($this->store->categories(), $filter, $update);
    }
}
