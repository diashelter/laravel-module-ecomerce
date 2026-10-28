<?php

use Tests\Unit\Architecture\Support\ReadModelRules;

/*
 * The read model of the storefront belongs to the catalog: the MongoDB connection, the two collections, their
 * repositories and the store are named by no other module, and inside the catalog only the projector consumers, the
 * console commands, the persistence layer and the provider reach them. One namespace and one target per expectation.
 */

it('keeps the read model inside the catalog', function () {
    expect(ReadModelRules::violations(base_path('app-modules')))->toBe([]);
});

arch('the catalog http layer does not reach the read model store')
    ->expect('Modules\Catalog\Http')
    ->not->toUse('Modules\Catalog\Persistence\ReadModel\ReadModelStore');

arch('the catalog http layer does not reach the product documents')
    ->expect('Modules\Catalog\Http')
    ->not->toUse('Modules\Catalog\Persistence\Repositories\ProductDocumentRepository');

arch('the catalog http layer does not reach the category documents')
    ->expect('Modules\Catalog\Http')
    ->not->toUse('Modules\Catalog\Persistence\Repositories\CategoryDocumentRepository');

arch('the catalog use cases do not reach the read model store')
    ->expect('Modules\Catalog\Core\UseCases')
    ->not->toUse('Modules\Catalog\Persistence\ReadModel\ReadModelStore');

arch('no other module names the read model store')
    ->expect('Modules\Inventory')
    ->not->toUse('Modules\Catalog\Persistence\ReadModel\ReadModelStore');

it('fails on a deliberate read model violation', function (string $fixture, string $expected) {
    $violations = ReadModelRules::violations(__DIR__.'/Fixtures/ReadModel/'.$fixture);

    expect($violations)->not->toBeEmpty()
        ->and(implode("\n", $violations))->toContain($expected);
})->with([
    'a class of another module that names a collection' => ['inventory-names-collection', 'outside the catalog'],
    'a controller of the catalog that writes into the read model' => ['http-writes-read-model', 'writes into the read model'],
]);
