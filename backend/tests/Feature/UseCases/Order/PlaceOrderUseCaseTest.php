<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ordering\Contracts\DeliveryAddressBook;
use Modules\Ordering\Contracts\ShippingQuoter;
use Modules\Ordering\Core\DTOs\CartDTO;
use Modules\Ordering\Core\DTOs\CartItemDTO;
use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Ordering\Core\UseCases\PlaceOrderUseCase;
use Modules\Ordering\Core\ValueObjects\DeliveryAddress;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;
use Modules\Ordering\Persistence\Models\Order;

/** An address book that answers the given address (or none), whoever asks. */
function fixedAddressBook(?DeliveryAddress $address): DeliveryAddressBook
{
    return new class($address) implements DeliveryAddressBook
    {
        public function __construct(private readonly ?DeliveryAddress $address) {}

        public function find(string $customerId, string $addressId): ?DeliveryAddress
        {
            return $this->address;
        }
    };
}

/** A quoter that always answers 999 cents in 3 days and counts how many times it was asked. */
function countingQuoter(): ShippingQuoter
{
    return new class implements ShippingQuoter
    {
        public int $calls = 0;

        public function quote(BrazilianState $state): ShippingQuote
        {
            $this->calls++;

            return new ShippingQuote(999, 3);
        }
    };
}

function placeOrderWith(DeliveryAddressBook $addressBook, ShippingQuoter $quoter): PlaceOrderUseCase
{
    return app()->make(PlaceOrderUseCase::class, ['addressBook' => $addressBook, 'shippingQuoter' => $quoter]);
}

it('places the order with the quote of the delivery state', function () {
    $user = customer();
    $product = productWithStock(5, ['price_cents' => 1000]);
    $address = new DeliveryAddress('Beto', '30140071', 'Av. Afonso Pena', '5', null, 'Centro', 'Belo Horizonte', BrazilianState::MG);

    $order = placeOrderWith(fixedAddressBook($address), countingQuoter())
        ->execute($user->id, new CartDTO(new CartItemDTO($product->id, 2)), unknownId());

    expect($order->shipping_cents)->toBe(999)
        ->and($order->delivery_business_days)->toBe(3)
        ->and($order->delivery_state)->toBe(BrazilianState::MG)
        ->and($order->total_cents)->toBe(2 * 1000 + 999);
});

it('refuses an unknown delivery address before touching the stock', function () {
    $product = productWithStock(5);
    $quoter = countingQuoter();
    DB::enableQueryLog();

    expect(fn () => placeOrderWith(fixedAddressBook(null), $quoter)
        ->execute(customer()->id, new CartDTO(new CartItemDTO($product->id, 2)), unknownId()))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toBe(['address_id' => ['Endereço de entrega não encontrado.']]);
        });

    $stockLocks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'from "stocks"') && str_contains($sql, 'for update'));

    expect($quoter->calls)->toBe(0)
        ->and($stockLocks)->toBeEmpty()
        ->and(stockOf($product)->quantity)->toBe(5)
        ->and(Order::query()->count())->toBe(0);
});

it('draws the order code up to three times', function (int $collisions, bool $placed) {
    $taken = array_slice(['AAAAAAAA', 'BBBBBBBB', 'CCCCCCCC'], 0, $collisions);
    foreach ($taken as $code) {
        Order::factory()->create(['code' => $code]);
    }
    $generator = forceOrderCodes([...$taken, 'EEEEEEEE', 'FFFFFFFF']);
    $user = customer();
    $product = productWithStock(5);
    $ordersBefore = Order::query()->count();
    $address = new DeliveryAddress('Beto', '30140071', 'Av. Afonso Pena', '5', null, 'Centro', 'Belo Horizonte', BrazilianState::MG);
    $place = fn () => placeOrderWith(fixedAddressBook($address), countingQuoter())
        ->execute($user->id, new CartDTO(new CartItemDTO($product->id, 2)), unknownId());

    if ($placed) {
        expect($place()->code)->toBe('EEEEEEEE')
            ->and($generator->draws)->toBe($collisions + 1)
            ->and(Order::query()->count())->toBe($ordersBefore + 1)
            ->and(stockOf($product)->quantity)->toBe(3)
            ->and(outboxMessages('ordering.order_placed'))->toHaveCount(1);

        return;
    }

    expect($place)->toThrow(UniqueConstraintViolationException::class);
    expect($generator->draws)->toBe(3)
        ->and(Order::query()->count())->toBe($ordersBefore)
        ->and(DB::table('order_items')->count())->toBe(0)
        ->and(stockOf($product)->quantity)->toBe(5)
        ->and(outboxMessages('ordering.order_placed'))->toBeEmpty();
})->with([
    'no collision' => [0, true],
    'one collision' => [1, true],
    'two collisions' => [2, true],
    'three collisions' => [3, false],
]);
