<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\InboundEvents\StockChanged;
use Modules\Catalog\Events\ProductCreated;
use Modules\Catalog\Events\ProductDeleted;
use Modules\Catalog\Events\ProductStatusChanged;
use Modules\Catalog\Events\ProductUpdated;
use Modules\Catalog\Persistence\Repositories\ProductDocumentRepository;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\RuntimeException;

/*
 * The projector keeps catalog_products from the events it is delivered, by the version of each part (AD-005). The
 * documents are built by deliveries through EventDelivery, the class both transports call.
 */

const PRODUCT_ID = '0199f0a0-0000-7000-8000-0000000000a1';

const PRODUCT_PART_KEYS = ['category_ids', 'created_at', 'description', 'image_url', 'name', 'price_cents', 'status', 'updated_at', 'version'];

it('replaces the product part with a newer product event', function (string $event) {
    // The part on disk has a field the event does not bring, and a description the event sets to null.
    readModel()->products()->insertOne([
        '_id' => PRODUCT_ID,
        'product' => ['name' => 'Antigo', 'description' => 'x', 'stale' => 'must not survive', 'version' => 1],
        'stock' => null,
        'deleted_version' => null,
    ]);

    project(productEvent(PRODUCT_ID, 2, $event, ['description' => null, 'categoryIds' => ['b', 'a']]));

    $product = productDocument(PRODUCT_ID)['product'];
    ksort($product);

    expect(array_keys($product))->toBe(PRODUCT_PART_KEYS)
        ->and($product['description'])->toBeNull()
        ->and($product['version'])->toBe(2)
        ->and($product['category_ids'])->toBe(['b', 'a'])
        ->and($product['status'])->toBe('active');
})->with([ProductCreated::class, ProductUpdated::class, ProductStatusChanged::class]);

it('creates the document when the first product event arrives', function (string $event) {
    project(productEvent(PRODUCT_ID, 1, $event));

    $document = productDocument(PRODUCT_ID);

    expect($document['product']['version'])->toBe(1)
        ->and($document['product']['name'])->toBe('Monitor 27')
        ->and($document['stock'])->toBeNull()
        ->and($document['deleted_version'])->toBeNull();
})->with([ProductCreated::class, ProductUpdated::class, ProductStatusChanged::class]);

it('discards a product event that is not newer', function (?int $stored, ?int $tombstone, int $incoming, bool $applied) {
    readModel()->products()->insertOne([
        '_id' => PRODUCT_ID,
        'product' => $stored === null ? null : ['name' => 'Guardado', 'version' => $stored],
        'stock' => null,
        'deleted_version' => $tombstone,
    ]);
    $before = productDocument(PRODUCT_ID);

    project(productEvent(PRODUCT_ID, $incoming, ProductUpdated::class, ['name' => 'Novo']));

    $after = productDocument(PRODUCT_ID);

    if ($applied) {
        expect($after['product']['name'])->toBe('Novo')->and($after['product']['version'])->toBe($incoming);
    } else {
        expect($after)->toBe($before);
    }
})->with([
    'version 2 stored, version 2 again' => [2, null, 2, false],
    'version 3 stored, version 1 late' => [3, null, 1, false],
    'version 2 stored, version 3' => [2, null, 3, true],
    'tombstone 4, version 4' => [null, 4, 4, false],
    'tombstone 4, version 3' => [null, 4, 3, false],
    'tombstone 4, version 5' => [null, 4, 5, true],
]);

it('creates the document with only the stock part when the stock arrives first', function () {
    project(stockEvent(PRODUCT_ID, 7, 3, 'stock-1'));

    $document = productDocument(PRODUCT_ID);

    expect(array_keys($document))->toEqualCanonicalizing(['_id', 'product', 'stock', 'deleted_version'])
        ->and($document['product'])->toBeNull()
        ->and($document['deleted_version'])->toBeNull()
        ->and($document['stock'])->toBe(['id' => 'stock-1', 'quantity' => 7, 'version' => 3]);
});

it('replaces or discards the stock part by its version', function (int $incoming, bool $applied) {
    project(stockEvent(PRODUCT_ID, 7, 3, 'stock-1'));

    project(stockEvent(PRODUCT_ID, 99, $incoming, 'stock-1'));

    expect(productDocument(PRODUCT_ID)['stock'])->toBe($applied
        ? ['id' => 'stock-1', 'quantity' => 99, 'version' => $incoming]
        : ['id' => 'stock-1', 'quantity' => 7, 'version' => 3]);
})->with([
    'greater' => [4, true],
    'equal' => [3, false],
    'lower' => [2, false],
]);

it('keeps the stock part when a product event replaces the product part and the other way around', function () {
    project(stockEvent(PRODUCT_ID, 7, 3));
    project(productEvent(PRODUCT_ID, 2));
    project(stockEvent(PRODUCT_ID, 5, 4));

    $document = productDocument(PRODUCT_ID);

    expect($document['product']['version'])->toBe(2)
        ->and($document['stock']['quantity'])->toBe(5);
});

it('keeps a deleted product as a tombstone', function () {
    // Delete version 4 before the creation version 1 and a stock event: the document stays a tombstone.
    project(new ProductDeleted(PRODUCT_ID, 4));
    $afterDelete = productDocument(PRODUCT_ID);

    project(productEvent(PRODUCT_ID, 1, ProductCreated::class));
    $afterLateCreate = productDocument(PRODUCT_ID);

    project(stockEvent(PRODUCT_ID, 9, 2, 'stock-1'));
    $afterStock = productDocument(PRODUCT_ID);

    expect($afterDelete['deleted_version'])->toBe(4)
        ->and($afterDelete['product'])->toBeNull()
        ->and($afterLateCreate)->toBe($afterDelete)
        ->and($afterStock['deleted_version'])->toBe(4)
        ->and($afterStock['product'])->toBeNull()
        ->and($afterStock['stock'])->toBe(['id' => 'stock-1', 'quantity' => 9, 'version' => 2]);
});

it('tombstones a listed product and discards the older tombstone', function () {
    project(productEvent(PRODUCT_ID, 2));

    project(new ProductDeleted(PRODUCT_ID, 3));
    project(new ProductDeleted(PRODUCT_ID, 2));

    $document = productDocument(PRODUCT_ID);

    expect($document['deleted_version'])->toBe(3)
        ->and($document['product']['version'])->toBe(2);
});

it('ends with the higher version when two deliveries race', function (bool $existing) {
    commitTestTransaction();

    foreach (range(1, 20) as $round) {
        $id = unknownId();
        if ($existing) {
            project(productEvent($id, 1));
        }
        $older = envelopeOf(productEvent($id, 2, ProductUpdated::class, ['name' => 'Versão 2']));
        $newer = envelopeOf(productEvent($id, 3, ProductUpdated::class, ['name' => 'Versão 3']));
        $stockOlder = envelopeOf(stockEvent($id, 1, 2));
        $stockNewer = envelopeOf(stockEvent($id, 9, 3));

        $failures = runConcurrently([
            fn () => redeliver($older, ProductUpdated::class),
            fn () => redeliver($newer, ProductUpdated::class),
            fn () => redeliver($stockOlder, StockChanged::class),
            fn () => redeliver($stockNewer, StockChanged::class),
        ]);

        $document = productDocument($id);

        expect($failures)->toBe([], "round {$round}")
            ->and($document['product']['version'])->toBe(3, "round {$round}")
            ->and($document['product']['name'])->toBe('Versão 3')
            ->and($document['stock']['version'])->toBe(3, "round {$round}")
            ->and($document['stock']['quantity'])->toBe(9);
    }
})->with(['with the document already there' => true, 'without the document' => false]);

it('counts a losing upsert as a discard', function () {
    project(productEvent(PRODUCT_ID, 2, ProductUpdated::class, ['name' => 'Versão 2']));
    $stored = productDocument(PRODUCT_ID);

    // The exact write of version 1: nothing matches the filter (the stored version is newer), the upsert then tries
    // to insert the same _id, and the duplicate key is the database saying the document is there.
    $losing = fn () => readModel()->products()->updateOne(
        ['_id' => PRODUCT_ID, '$and' => [
            ['$or' => [['product' => null], ['product.version' => ['$lt' => 1]]]],
            ['$or' => [['deleted_version' => null], ['deleted_version' => ['$lt' => 1]]]],
        ]],
        ['$set' => ['product' => ['version' => 1]], '$setOnInsert' => ['stock' => null, 'deleted_version' => null]],
        ['upsert' => true],
    );

    expect($losing)->toThrow(BulkWriteException::class, 'E11000');

    // The repository tries again (the document might have lost an insert race instead) and, with the document
    // there and newer, takes the second duplicate key as a discard, and so does the delivery.
    expect(app(ProductDocumentRepository::class)->replaceProductPart(PRODUCT_ID, ['name' => 'Versão 1', 'version' => 1]))->toBeFalse();

    project(productEvent(PRODUCT_ID, 1, ProductUpdated::class, ['name' => 'Versão 1']));

    expect(productDocument(PRODUCT_ID))->toBe($stored);
});

it('fails the delivery and keeps the document when MongoDB does not answer', function () {
    project(productEvent(PRODUCT_ID, 1));
    $stored = productDocument(PRODUCT_ID);
    $working = config('database.connections.mongodb');

    // A port nothing listens on, and a short wait for it.
    config(['database.connections.mongodb.host' => '127.0.0.1', 'database.connections.mongodb.port' => 1, 'database.connections.mongodb.options.serverSelectionTimeoutMS' => 300, 'database.connections.mongodb.options.connectTimeoutMS' => 300]);
    DB::purge('mongodb');

    try {
        expect(fn () => project(productEvent(PRODUCT_ID, 2)))->toThrow(RuntimeException::class);
    } finally {
        config(['database.connections.mongodb' => $working]);
        DB::purge('mongodb');
    }

    expect(productDocument(PRODUCT_ID))->toBe($stored);
});

it('stores exactly the fields of door 4', function () {
    project(productEvent(PRODUCT_ID, 2, ProductCreated::class, ['categoryIds' => ['c1', 'c2']]));
    project(stockEvent(PRODUCT_ID, 5, 3, 'stock-1'));
    project(categoryEvent('0199f0a0-0000-7000-8000-0000000000c1', 1));

    $product = productDocument(PRODUCT_ID);
    $category = categoryDocument('0199f0a0-0000-7000-8000-0000000000c1');
    ksort($product['product']);
    ksort($product['stock']);
    ksort($category);

    expect(array_keys($product))->toEqualCanonicalizing(['_id', 'product', 'stock', 'deleted_version'])
        ->and(array_keys($product['product']))->toBe(PRODUCT_PART_KEYS)
        ->and($product['product']['created_at'])->toBeString()->toBe('2026-10-27T10:00:00+00:00')
        ->and($product['product']['updated_at'])->toBeString()->toBe('2026-10-27T11:00:00+00:00')
        ->and(array_keys($product['stock']))->toBe(['id', 'quantity', 'version'])
        ->and(array_keys($category))->toBe(['_id', 'deleted_version', 'name', 'slug', 'version'])
        ->and(json_encode([$product, $category]))->not->toContain('is_available')->not->toContain('status_label')->not->toContain('available_quantity');
});
