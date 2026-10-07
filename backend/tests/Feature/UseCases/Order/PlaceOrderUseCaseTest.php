<?php

use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\DTOs\CartItemDTO;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\UseCases\PlaceOrderUseCase;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Ordering\ValueObjects\ShippingQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Event::fake([OrderPlaced::class]));

/** An address book that answers the given address (or none), whoever asks. */
function fixedAddressBook(?DeliveryAddress $address): DeliveryAddressBook
{
    return new class($address) implements DeliveryAddressBook
    {
        public function __construct(private readonly ?DeliveryAddress $address) {}

        public function find(int $customerId, int $addressId): ?DeliveryAddress
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
        ->execute($user->id, new CartDTO(new CartItemDTO($product->id, 2)), 123);

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
        ->execute(customer()->id, new CartDTO(new CartItemDTO($product->id, 2)), 123))
        ->toThrow(function (ValidationException $e) {
            expect($e->errors())->toBe(['address_id' => ['Endereço de entrega não encontrado.']]);
        });

    $stockLocks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'from "stocks"') && str_contains($sql, 'for update'));

    expect($quoter->calls)->toBe(0)
        ->and($stockLocks)->toBeEmpty()
        ->and($product->stock->fresh()->quantity)->toBe(5)
        ->and(Order::query()->count())->toBe(0);
});
