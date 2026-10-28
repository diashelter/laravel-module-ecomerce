<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Events\ProductDeleted;

/*
 * The storefront reads the read model and nothing else (S4 of the storefront read model). The documents are built by
 * deliveries of the events, as in production; the routes are the public ones.
 */

/** A listed product: the product event and, unless the quantity is null, the stock event. */
function listProduct(string $name, int $priceCents = 1000, ?int $quantity = 5, array $categoryIds = [], string $status = 'active', ?string $id = null): string
{
    $id ??= unknownId();
    project(productEvent($id, 1, overrides: ['name' => $name, 'priceCents' => $priceCents, 'categoryIds' => $categoryIds, 'status' => $status]));

    if ($quantity !== null) {
        project(stockEvent($id, $quantity, 1, unknownId()));
    }

    return $id;
}

function storefrontNames(string $query = ''): array
{
    return array_column(test()->getJson('/api/products'.$query)->assertOk()->json('data'), 'name');
}

it('lists only the documents with a product part and no tombstone twelve per page', function () {
    foreach (range(1, 13) as $number) {
        listProduct(sprintf('Produto %02d', $number), $number * 100);
    }
    project(stockEvent(unknownId(), 4, 1, unknownId())); // only the stock part
    $deleted = listProduct('Excluído');
    project(new ProductDeleted($deleted, 2)); // a tombstone

    $first = $this->getJson('/api/products')->assertOk();

    expect($first->json('meta.total'))->toBe(13)
        ->and($first->json('meta.last_page'))->toBe(2)
        ->and($first->json('meta.per_page'))->toBe(12)
        ->and($first->json('data'))->toHaveCount(12)
        ->and(array_column($first->json('data'), 'name'))->not->toContain('Excluído');

    $this->getJson('/api/products?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Produto 13');

    $sorted = $this->getJson('/api/products?sort=price_asc&page=1')->assertOk();
    expect($sorted->json('links.next'))->toContain('sort=price_asc')->toContain('page=2');
});

it('filters by the slug of a category that is not a tombstone', function () {
    $live = '0199f0a0-0000-7000-8000-0000000000c1';
    $dead = '0199f0a0-0000-7000-8000-0000000000c2';
    $reborn = '0199f0a0-0000-7000-8000-0000000000c3';
    project(categoryEvent($live, 1, 'Games', 'games'));
    project(categoryEvent($dead, 1, 'Velha', 'velha'));
    project(new CategoryDeleted($dead, 2));
    listProduct('Em games', categoryIds: [$live]);
    listProduct('Na velha', categoryIds: [$dead]);
    listProduct('Sem categoria');

    // A live category: its products.
    expect(storefrontNames('?category=games'))->toBe(['Em games']);

    // A slug only a tombstone holds, and an unknown slug: an empty page, not an error.
    foreach (['velha', 'desconhecida'] as $slug) {
        $this->getJson("/api/products?category={$slug}")->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    // A new category that reuses the slug of the tombstone: only the products of the new one.
    project(categoryEvent($reborn, 1, 'Velha 2', 'velha', CategoryCreated::class));
    listProduct('Na nova', categoryIds: [$reborn]);

    expect(storefrontNames('?category=velha'))->toBe(['Na nova']);
});

it('sorts the storefront by name in byte order and by price with the id breaking ties', function () {
    foreach (['banana', 'Zebra', 'Abacaxi', 'éclair', 'Banana', 'Água', 'caju'] as $name) {
        listProduct($name);
    }

    expect(storefrontNames('?sort=name'))->toBe(['Abacaxi', 'Banana', 'Zebra', 'banana', 'caju', 'Água', 'éclair'])
        ->and(storefrontNames())->toBe(['Abacaxi', 'Banana', 'Zebra', 'banana', 'caju', 'Água', 'éclair']);

    DB::connection('mongodb')->disableQueryLog();
    readModel()->products()->deleteMany([]);
    // Same price and same name: the smaller id first, in the three sorts.
    $ids = ['0199f0a0-0000-7000-8000-000000000003', '0199f0a0-0000-7000-8000-000000000001', '0199f0a0-0000-7000-8000-000000000002'];
    foreach ($ids as $id) {
        listProduct('Igual', 500, id: $id);
    }
    listProduct('Barato', 100);
    listProduct('Caro', 900);

    $order = fn (string $sort) => array_column($this->getJson("/api/products?sort={$sort}")->assertOk()->json('data'), 'id');
    $sameIds = array_values(array_filter($order('name'), fn (string $id) => in_array($id, $ids, true)));
    $byPrice = collect($this->getJson('/api/products?sort=price_asc')->json('data'))->pluck('price_cents')->all();

    expect($sameIds)->toBe(['0199f0a0-0000-7000-8000-000000000001', '0199f0a0-0000-7000-8000-000000000002', '0199f0a0-0000-7000-8000-000000000003'])
        ->and($byPrice)->toBe([100, 500, 500, 500, 900])
        ->and(array_values(array_filter($order('price_asc'), fn (string $id) => in_array($id, $ids, true))))->toBe(['0199f0a0-0000-7000-8000-000000000001', '0199f0a0-0000-7000-8000-000000000002', '0199f0a0-0000-7000-8000-000000000003'])
        ->and(array_values(array_filter($order('price_desc'), fn (string $id) => in_array($id, $ids, true))))->toBe(['0199f0a0-0000-7000-8000-000000000001', '0199f0a0-0000-7000-8000-000000000002', '0199f0a0-0000-7000-8000-000000000003'])
        ->and(collect($this->getJson('/api/products?sort=price_desc')->json('data'))->pluck('price_cents')->all())->toBe([900, 500, 500, 500, 100]);
});

it('refuses an invalid sort or page', function (string $query, string $field) {
    $this->getJson("/api/products?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'sort random' => ['sort=random', 'sort'],
    'page zero' => ['page=0', 'page'],
    'page text' => ['page=abc', 'page'],
    'page past the limit' => ['page=9223372036854775807', 'page'],
]);

it('answers the product page from the document', function (string $state, int $status, string $message) {
    $id = match ($state) {
        'available' => listProduct('Disponível', quantity: 3),
        'inactive' => listProduct('Inativo', quantity: 4, status: 'inactive'),
        'no units' => listProduct('Esgotado', quantity: 0),
        'no stock part' => listProduct('Sem estoque', quantity: null),
        'unknown' => unknownId(),
        'tombstone' => (function () {
            $id = listProduct('Lápide');
            project(new ProductDeleted($id, 2));

            return $id;
        })(),
        'only stock' => (function () {
            $id = unknownId();
            project(stockEvent($id, 5, 1));

            return $id;
        })(),
    };

    $response = $this->getJson("/api/products/{$id}")->assertStatus($status);

    if ($status === 200) {
        $response->assertJsonPath('data.available_quantity', 3)->assertJsonPath('data.id', $id);
    } else {
        $response->assertJsonPath('message', $message);
    }
})->with([
    'available' => ['available', 200, ''],
    'inactive' => ['inactive', 404, 'Produto indisponível.'],
    'no units' => ['no units', 404, 'Produto indisponível.'],
    'no stock part' => ['no stock part', 404, 'Produto indisponível.'],
    'unknown' => ['unknown', 404, 'Recurso não encontrado.'],
    'tombstone' => ['tombstone', 404, 'Recurso não encontrado.'],
    'only stock' => ['only stock', 404, 'Recurso não encontrado.'],
]);

it('answers 404 to a non canonical id without reading MongoDB', function () {
    $connection = DB::connection('mongodb');
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $this->getJson('/api/products/abc')->assertNotFound();

    expect($connection->getQueryLog())->toBe([]);
});

it('renders a document without the stock part as unavailable', function () {
    $id = listProduct('Sem estoque', quantity: null);

    $item = $this->getJson('/api/products')->assertOk()->json('data.0');

    expect($item['id'])->toBe($id)
        ->and($item['stock'])->toBeNull()
        ->and($item['is_available'])->toBeFalse()
        ->and($item['available_quantity'])->toBe(0);
});

it('leaves out a category that has not arrived or is a tombstone', function () {
    [$first, $missing, $dead, $last] = ['0199f0a0-0000-7000-8000-0000000000d1', '0199f0a0-0000-7000-8000-0000000000d2', '0199f0a0-0000-7000-8000-0000000000d3', '0199f0a0-0000-7000-8000-0000000000d4'];
    project(categoryEvent($first, 1, 'Primeira', 'primeira'));
    project(categoryEvent($dead, 1, 'Morta', 'morta'));
    project(new CategoryDeleted($dead, 2));
    project(categoryEvent($last, 1, 'Última', 'ultima'));
    listProduct('Com categorias', categoryIds: [$last, $missing, $dead, $first]);

    $categories = $this->getJson('/api/products')->assertOk()->json('data.0.categories');

    expect(array_column($categories, 'slug'))->toBe(['ultima', 'primeira'])
        ->and(array_keys($categories[0]))->toBe(['id', 'name', 'slug']);
});

it('reads MongoDB the same number of times for one and twelve products', function () {
    $category = '0199f0a0-0000-7000-8000-0000000000d1';
    project(categoryEvent($category, 1));
    $other = '0199f0a0-0000-7000-8000-0000000000d2';
    project(categoryEvent($other, 1, 'Outra', 'outra'));
    $reads = function () {
        $connection = DB::connection('mongodb');
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $this->getJson('/api/products')->assertOk();

        return count($connection->getQueryLog());
    };

    listProduct('Um', categoryIds: [$category, $other]);
    $single = $reads();

    foreach (range(1, 11) as $number) {
        listProduct("Produto {$number}", categoryIds: [$category, $other]);
    }

    expect($single)->toBeGreaterThan(0)->and($reads())->toBe($single);
});

it('runs no PostgreSQL query on the storefront', function () {
    $id = listProduct('Qualquer');
    project(categoryEvent('0199f0a0-0000-7000-8000-0000000000d1', 1, 'Games', 'games'));
    DB::connection('pgsql')->flushQueryLog();
    DB::connection('pgsql')->enableQueryLog();

    $this->getJson('/api/products')->assertOk();
    $this->getJson('/api/products?category=games&sort=price_desc&page=1')->assertOk();
    $this->getJson("/api/products/{$id}")->assertOk();

    expect(DB::connection('pgsql')->getQueryLog())->toBe([]);
});

it('decides availability when reading', function () {
    listProduct('Ativo sem unidades', quantity: 0);
    listProduct('Inativo com unidades', quantity: 5, status: 'inactive');

    $items = collect($this->getJson('/api/products')->assertOk()->json('data'))->keyBy('name');

    expect($items)->toHaveCount(2)
        ->and($items['Ativo sem unidades']['is_available'])->toBeFalse()
        ->and($items['Ativo sem unidades']['status_label'])->toBe('Ativo')
        ->and($items['Inativo com unidades']['is_available'])->toBeFalse()
        ->and($items['Inativo com unidades']['available_quantity'])->toBe(5);
});

it('answers 503 within five seconds when MongoDB does not answer', function (string $uri) {
    $id = listProduct('Qualquer');
    $working = config('database.connections.mongodb');
    config(['database.connections.mongodb.host' => '127.0.0.1', 'database.connections.mongodb.port' => 1]);
    DB::purge('mongodb');
    $started = microtime(true);

    try {
        $response = $this->getJson(str_replace('{id}', $id, $uri))->assertStatus(503);
        $elapsed = microtime(true) - $started;
    } finally {
        config(['database.connections.mongodb' => $working]);
        DB::purge('mongodb');
    }

    assertApiError($response, 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.');
    expect($elapsed)->toBeLessThan(5.0);
})->with(['/api/products', '/api/products/{id}']);

it('counts the filtered list in MongoDB and reads the same number of times for one and twelve products', function () {
    $games = '0199f0a0-0000-7000-8000-0000000000c1';
    project(categoryEvent($games, 1, 'Games', 'games'));
    foreach (range(1, 14) as $number) {
        listProduct(sprintf('Em games %02d', $number), categoryIds: [$games]);
    }
    foreach (range(1, 5) as $number) {
        listProduct("Fora {$number}");
    }

    $first = $this->getJson('/api/products?category=games')->assertOk();
    $second = $this->getJson('/api/products?category=games&page=2')->assertOk();

    expect($first->json('meta.total'))->toBe(14)
        ->and($first->json('meta.last_page'))->toBe(2)
        ->and($first->json('data'))->toHaveCount(12)
        ->and($first->json('links.next'))->toContain('category=games')->toContain('page=2')
        ->and($second->json('data'))->toHaveCount(2)
        ->and($second->json('links.prev'))->toContain('category=games');

    // The filtered path costs a fixed number of reads too: the slug, the count, the page and the categories.
    $reads = function (string $slug) {
        $connection = DB::connection('mongodb');
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $this->getJson("/api/products?category={$slug}")->assertOk();

        return count($connection->getQueryLog());
    };
    $twelve = $reads('games');
    readModel()->products()->deleteMany([]);
    listProduct('Único em games', categoryIds: [$games]);

    expect($reads('games'))->toBe($twelve);
});
