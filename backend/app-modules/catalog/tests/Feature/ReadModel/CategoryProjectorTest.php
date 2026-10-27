<?php

use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Events\CategoryUpdated;

const CATEGORY_ID = '0199f0a0-0000-7000-8000-0000000000c1';

/** The stored category without the noise of the driver: name, slug, version and tombstone. */
function categoryState(): array
{
    $document = categoryDocument(CATEGORY_ID);

    return [$document['name'], $document['slug'], $document['version'], $document['deleted_version']];
}

it('projects categories by the same version rule and touches no product', function () {
    // A product document with a category id in it must stay exactly as it is.
    project(productEvent(unknownId(), 1, overrides: ['categoryIds' => [CATEGORY_ID]]));
    $products = readModel()->products()->find([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();

    // Created, then renamed with a greater version: replaced.
    project(categoryEvent(CATEGORY_ID, 1, 'Eletrônicos', 'eletronicos', CategoryCreated::class));
    expect(categoryState())->toBe(['Eletrônicos', 'eletronicos', 1, null]);

    project(categoryEvent(CATEGORY_ID, 3, 'Casa e Jardim', 'casa-e-jardim'));
    expect(categoryState())->toBe(['Casa e Jardim', 'casa-e-jardim', 3, null]);

    // An older rename and the same version again: discarded.
    project(categoryEvent(CATEGORY_ID, 2, 'Antigo', 'antigo'));
    project(categoryEvent(CATEGORY_ID, 3, 'Repetido', 'repetido'));
    expect(categoryState())->toBe(['Casa e Jardim', 'casa-e-jardim', 3, null]);

    // Deleted: a tombstone. A rename that arrives late, even a newer version than the stored one, stays out.
    project(new CategoryDeleted(CATEGORY_ID, 4));
    expect(categoryState())->toBe(['Casa e Jardim', 'casa-e-jardim', 3, 4]);

    project(categoryEvent(CATEGORY_ID, 4, 'Tarde', 'tarde'));
    expect(categoryState())->toBe(['Casa e Jardim', 'casa-e-jardim', 3, 4]);

    expect(readModel()->products()->find([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray())->toBe($products);
});

it('leaves a tombstone when the deletion arrives before the category', function () {
    project(new CategoryDeleted(CATEGORY_ID, 5));
    project(categoryEvent(CATEGORY_ID, 1, 'Eletrônicos', 'eletronicos', CategoryCreated::class));
    project(categoryEvent(CATEGORY_ID, 4, 'Eletrônicos', 'eletronicos', CategoryUpdated::class));

    $document = categoryDocument(CATEGORY_ID);

    expect($document['deleted_version'])->toBe(5)
        ->and($document['name'])->toBeNull()
        ->and($document['slug'])->toBeNull()
        ->and($document['version'])->toBeNull();
});
