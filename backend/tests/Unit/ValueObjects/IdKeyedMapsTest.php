<?php

use Illuminate\Support\Collection;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Ordering\Core\DTOs\CartItemDTO;
use Modules\Ordering\Core\ValueObjects\OrderCountsByCustomer;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;

const MAP_FIRST_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a';

const MAP_SECOND_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0b';

const MAP_ABSENT_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0c';

it('keys the id maps by canonical uuid', function (Closure $build, Closure $lookup, array $expected) {
    $map = $build();

    expect(iterator_to_array($map))->toBe($expected)
        ->and(count($map))->toBe(2)
        ->and($lookup($map, MAP_FIRST_ID))->toBe($expected[MAP_FIRST_ID])
        ->and($lookup($map, MAP_SECOND_ID))->toBe($expected[MAP_SECOND_ID])
        ->and($lookup($map, MAP_ABSENT_ID))->toBe(0);
})->with([
    'stock quantities' => [
        fn () => new StockQuantities(new Collection([MAP_FIRST_ID => '7', MAP_SECOND_ID => '3'])),
        fn (StockQuantities $map, string $id) => $map->of($id),
        [MAP_FIRST_ID => 7, MAP_SECOND_ID => 3],
    ],
    'order counts by customer' => [
        fn () => new OrderCountsByCustomer(new Collection([MAP_FIRST_ID => '4', MAP_SECOND_ID => '1'])),
        fn (OrderCountsByCustomer $map, string $id) => $map->countFor($id),
        [MAP_FIRST_ID => 4, MAP_SECOND_ID => 1],
    ],
]);

it('keys the id maps by canonical uuid in the product quantities', function () {
    $quantities = new ProductQuantities(
        new CartItemDTO(MAP_SECOND_ID, 1),
        new CartItemDTO(MAP_FIRST_ID, 2),
        new CartItemDTO(MAP_SECOND_ID, 5),
    );

    expect(iterator_to_array($quantities))->toBe([MAP_FIRST_ID => 2, MAP_SECOND_ID => 6])
        ->and(count($quantities))->toBe(2)
        ->and($quantities->productIds()->all())->toBe([MAP_FIRST_ID, MAP_SECOND_ID]);
});
