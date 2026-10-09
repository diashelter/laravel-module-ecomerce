<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer: places an order atomically. Stock check, stock decrement and order creation
 * happen inside a single database transaction with the stock rows locked. The order keeps a copy
 * of the chosen delivery address and the shipping quoted for its state.
 */
final class PlaceOrderUseCase
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly StockReservation $stockReservation,
        private readonly OrderRepository $orders,
        private readonly CheckoutService $checkout,
        private readonly DeliveryAddressBook $addressBook,
        private readonly ShippingQuoter $shippingQuoter,
    ) {}

    /**
     * @throws InsufficientStockException
     * @throws ValidationException when the address is not in the customer's own address book
     */
    public function execute(int $customerId, CartDTO $cart, int $addressId): Order
    {
        // The address comes first: an unknown one must never lock or touch the stock.
        $address = $this->addressBook->find($customerId, $addressId)
            ?? throw ValidationException::withMessages(['address_id' => ['Endereço de entrega não encontrado.']]);

        // The server quotes the shipping from the address state: the client never sends it.
        $shipping = $this->shippingQuoter->quote($address->state);

        $quantities = $cart->quantities();

        $order = DB::transaction(function () use ($customerId, $quantities, $address, $shipping): Order {
            $productIds = $quantities->productIds();

            // 1. Lock the stock rows (SELECT ... FOR UPDATE). Any concurrent checkout touching
            //    the same products waits here until this transaction commits or rolls back.
            //    Rows are always locked in the same order (by product_id) to avoid deadlocks.
            $stock = $this->stockReservation->lockForProducts($productIds);

            $products = $this->catalog->findMany($productIds);

            // 2. Check availability again, now with the locked (up-to-date) quantities.
            //    Throwing inside DB::transaction() rolls everything back.
            $this->checkout->assertCanFulfil($quantities, $products, $stock);

            // 3. Prices come from the catalog.
            $lines = $this->checkout->buildOrderLines($quantities, $products);

            // 4. UPDATE stocks SET quantity = quantity - ? (safe: the rows are locked).
            foreach ($quantities as $productId => $quantity) {
                $this->stockReservation->decrement($productId, $quantity);
            }

            // 5. Create the order and its items (snapshot of name and price), with the address copy and
            //    the shipping, so the total is the items plus the shipping.
            return $this->orders->createWithItems($customerId, OrderStatus::Placed, $lines, $address, $shipping);
        });

        // 6. Only after COMMIT: the queued listener moves the order to "awaiting_payment".
        OrderPlaced::dispatch($order->id);

        return $order->load('items');
    }
}
