<?php

use App\Modules\Catalog\ValueObjects\CategoryIds;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\DTOs\CartItemDTO;
use App\Modules\Ordering\ValueObjects\CustomerIds;
use App\Modules\Ordering\ValueObjects\OrderCountsByCustomer;
use App\Modules\Ordering\ValueObjects\OrderLine;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use App\Modules\Ordering\ValueObjects\ValidatedCart;
use App\Modules\Ordering\ValueObjects\ValidatedCartLine;

it('groups the cart into product quantities in ascending product order', function () {
    $cart = new CartDTO(new CartItemDTO(7, 1), new CartItemDTO(3, 1), new CartItemDTO(7, 5));

    expect(iterator_to_array($cart->quantities()))->toBe([3 => 1, 7 => 6])
        ->and($cart->quantities()->productIds()->all())->toBe([3, 7]);
});

$line = fn (?string $problem, int $unitPrice, int $quantity = 1) => new ValidatedCartLine(
    productId: 1, name: 'P', imageUrl: null, unitPriceCents: $unitPrice, quantity: $quantity,
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
    $line = new OrderLine(1, 'Phone', 19990, 3);

    expect($line->subtotalCents())->toBe(59970)
        ->and((new OrderLines($line, new OrderLine(2, 'Case', 1000, 2)))->totalCents())->toBe(61970);
});

it('refuses an element of another type in a typed list', function (string $class) {
    new $class(new stdClass);
})->with([CartDTO::class, OrderLines::class, ValidatedCart::class])->throws(TypeError::class);

it('refuses an invalid id in a typed id list', function (string $class, array $ids) {
    new $class(...$ids);
})->with([ProductIds::class, CategoryIds::class, CustomerIds::class])
    ->with(['zero' => [[0]], 'negative' => [[-1]], 'repeated' => [[4, 4]]])
    ->throws(InvalidArgumentException::class);

it('answers zero orders for a customer without orders', function () {
    $counts = new OrderCountsByCustomer(collect([5 => 3]));

    expect($counts->countFor(5))->toBe(3)->and($counts->countFor(6))->toBe(0);
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
