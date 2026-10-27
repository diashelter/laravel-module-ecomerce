<?php

use Illuminate\Support\Facades\Artisan;
use MongoDB\Collection;

/** The indexes of a collection as [name => keys], without the options the server adds. */
function indexesOf(Collection $collection): array
{
    $indexes = [];

    foreach ($collection->listIndexes() as $index) {
        $indexes[$index->getName()] = ['key' => (array) $index->getKey(), 'unique' => $index->isUnique()];
    }

    ksort($indexes);

    return $indexes;
}

it('empties both collections and leaves the indexes of door 4', function () {
    project(productEvent(unknownId(), 1));
    project(stockEvent(unknownId(), 3, 1));
    project(categoryEvent(unknownId(), 1));

    $exit = Artisan::call('catalog:read-model-clear');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Modelo de leitura esvaziado: catalog_products e catalog_categories.')
        ->and(readModel()->products()->countDocuments([]))->toBe(0)
        ->and(readModel()->categories()->countDocuments([]))->toBe(0)
        ->and(indexesOf(readModel()->products()))->toBe([
            '_id_' => ['key' => ['_id' => 1], 'unique' => false],
            'product.category_ids_1' => ['key' => ['product.category_ids' => 1], 'unique' => false],
            'product.name_1__id_1' => ['key' => ['product.name' => 1, '_id' => 1], 'unique' => false],
            'product.price_cents_1__id_1' => ['key' => ['product.price_cents' => 1, '_id' => 1], 'unique' => false],
        ])
        ->and(indexesOf(readModel()->categories()))->toBe([
            '_id_' => ['key' => ['_id' => 1], 'unique' => false],
            'slug_1' => ['key' => ['slug' => 1], 'unique' => false],
        ]);

    // Run again, with the collections already empty: the same indexes, still no document.
    expect(Artisan::call('catalog:read-model-clear'))->toBe(0)
        ->and(array_keys(indexesOf(readModel()->products())))->toHaveCount(4);
});
