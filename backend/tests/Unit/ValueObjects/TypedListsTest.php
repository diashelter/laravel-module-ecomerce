<?php

use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Ordering\Core\DTOs\CartDTO;
use Modules\Ordering\Core\DTOs\CartItemDTO;
use Modules\Ordering\Core\ValueObjects\CustomerIds;
use Modules\Ordering\Core\ValueObjects\OrderCountsByCustomer;
use Modules\Ordering\Core\ValueObjects\OrderLine;
use Modules\Ordering\Core\ValueObjects\OrderLines;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;
use Modules\Ordering\Core\ValueObjects\ValidatedCartLine;

const FIRST_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a';

const SECOND_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0b';

const UPPERCASE_UUID = '0199E3B0-4C2A-7D10-9A3B-5F6E7D8C9B0A';

it('groups the cart into product quantities in ascending product order', function () {
    $cart = new CartDTO(new CartItemDTO(SECOND_ID, 1), new CartItemDTO(FIRST_ID, 1), new CartItemDTO(SECOND_ID, 5));

    expect(iterator_to_array($cart->quantities()))->toBe([FIRST_ID => 1, SECOND_ID => 6])
        ->and($cart->quantities()->productIds()->all())->toBe([FIRST_ID, SECOND_ID]);
});

$line = fn (?string $problem, int $unitPrice, int $quantity = 1) => new ValidatedCartLine(
    productId: FIRST_ID, name: 'P', imageUrl: null, unitPriceCents: $unitPrice, quantity: $quantity,
    availableQuantity: 5, isAvailable: true, problem: $problem,
);

it('derives the validated cart total from the lines without a problem', function () use ($line) {
    $cart = new ValidatedCart($line(null, 1000, 2), $line('Estoque insuficiente. Disponível: 0.', 500));

    expect($cart->totalCents())->toBe(2000)->and($cart->isValid())->toBeFalse();
});

it('treats an empty validated cart as invalid', function () {
    $cart = new ValidatedCart;

    expect($cart->totalCents())->toBe(0)->and($cart->isValid())->toBeFalse();
});

it('derives the order line subtotal and the order total', function () {
    $line = new OrderLine(FIRST_ID, 'Phone', 19990, 3);

    expect($line->subtotalCents())->toBe(59970)
        ->and((new OrderLines($line, new OrderLine(SECOND_ID, 'Case', 1000, 2)))->totalCents())->toBe(61970);
});

it('refuses an element of another type in a typed list', function (string $class) {
    new $class(new stdClass);
})->with([CartDTO::class, OrderLines::class, ValidatedCart::class])->throws(TypeError::class);

it('refuses an invalid id in a typed id list', function (string $class, array $ids) {
    new $class(...$ids);
})->with([ProductIds::class, CategoryIds::class, CustomerIds::class])
    ->with([
        'numeric string' => [['1']],
        'empty string' => [['']],
        'word' => [['abc']],
        'uppercase uuid' => [[UPPERCASE_UUID]],
        'repeated' => [[FIRST_ID, FIRST_ID]],
    ])
    ->throws(InvalidArgumentException::class);

it('accepts canonical uuids in a typed id list', function (string $class) {
    $list = new $class(SECOND_ID, FIRST_ID);

    expect($list->all())->toBe([SECOND_ID, FIRST_ID])
        ->and($list->count())->toBe(2);
})->with([ProductIds::class, CategoryIds::class, CustomerIds::class]);

it('answers zero orders for a customer without orders', function () {
    $counts = new OrderCountsByCustomer(collect([FIRST_ID => 3]));

    expect($counts->countFor(FIRST_ID))->toBe(3)->and($counts->countFor(SECOND_ID))->toBe(0);
});

it('typed lists are final readonly iterable and countable', function (string $class, bool $isList) {
    $reflection = new ReflectionClass($class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue()
        ->and($reflection->implementsInterface(IteratorAggregate::class))->toBe($isList)
        ->and($reflection->implementsInterface(Countable::class))->toBe($isList);
})->with([
    [CartDTO::class, true], [ProductQuantities::class, true], [ProductIds::class, true],
    [CategoryIds::class, true], [OrderLines::class, true], [ValidatedCart::class, true],
    [CustomerIds::class, true], [OrderCountsByCustomer::class, true],
    [OrderLine::class, false], [ValidatedCartLine::class, false],
]);
