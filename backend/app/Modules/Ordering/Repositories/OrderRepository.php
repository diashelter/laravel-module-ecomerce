<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Repositories;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Ordering\ValueObjects\CustomerIds;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Ordering\ValueObjects\OrderCountsByCustomer;
use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Ordering\ValueObjects\OrderLine;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\OrderSummaries;
use App\Modules\Ordering\ValueObjects\OrderSummary;
use App\Modules\Ordering\ValueObjects\ShippingQuote;
use App\Modules\Shared\Repositories\BaseRepository;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Also answers the catalog's ProductOrderHistory contract and the ordering contracts other modules
 * use (bound in OrderingServiceProvider).
 *
 * @extends BaseRepository<Order>
 */
class OrderRepository extends BaseRepository implements CustomerOrderHistory, PayableOrders, ProductOrderHistory
{
    protected function model(): string
    {
        return Order::class;
    }

    /** @return LengthAwarePaginator<int, Order> */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->newestFirst($this->query())
            ->with('customer')
            ->withCount('items')
            ->paginate($perPage);
    }

    /** @return LengthAwarePaginator<int, Order> */
    public function paginateForCustomer(string $customerId, int $perPage): LengthAwarePaginator
    {
        return $this->newestFirst($this->forCustomer($customerId))
            ->withCount('items')
            ->paginate($perPage);
    }

    public function recentForCustomer(string $customerId, int $limit): OrderSummaries
    {
        return new OrderSummaries(...$this->newestFirst($this->forCustomer($customerId))
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => new OrderSummary($order->id, $order->code, $order->status, $order->total_cents, $order->created_at->toImmutable()))
            ->all());
    }

    public function countForCustomer(string $customerId): int
    {
        return $this->forCustomer($customerId)->count();
    }

    /**
     * Order count of several customers in a single query (no N+1 on listings).
     * Customers without orders have no row: the result answers zero for them.
     */
    public function countPerCustomer(CustomerIds $customerIds): OrderCountsByCustomer
    {
        return new OrderCountsByCustomer($this->query()
            ->whereIn('customer_id', $customerIds->all())
            ->selectRaw('customer_id, count(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id'));
    }

    /**
     * Whether the product appears in any order. Items keep a snapshot of the product, but the
     * catalog must not delete a product that order history still points to.
     */
    public function hasBeenOrdered(string $productId): bool
    {
        return OrderItem::query()->where('product_id', $productId)->exists();
    }

    /**
     * Creates the order, with the code already drawn, and its items (snapshot of name and price)
     * together with the copy of the delivery address and the shipping quoted for it. The order total is the one the lines and
     * the shipping calculate, so it cannot diverge from them.
     */
    public function createWithItems(
        string $customerId,
        string $code,
        OrderStatus $status,
        OrderLines $lines,
        DeliveryAddress $address,
        ShippingQuote $shipping,
    ): Order {
        $order = $this->query()->create([
            'code' => $code,
            'customer_id' => $customerId,
            'total_cents' => $lines->totalCents() + $shipping->priceCents,
            'shipping_cents' => $shipping->priceCents,
            'delivery_business_days' => $shipping->deliveryBusinessDays,
            'delivery_recipient_name' => $address->recipientName,
            'delivery_postal_code' => $address->postalCode,
            'delivery_street' => $address->street,
            'delivery_number' => $address->number,
            'delivery_complement' => $address->complement,
            'delivery_district' => $address->district,
            'delivery_city' => $address->city,
            'delivery_state' => $address->state,
            'status' => $status,
        ]);
        $order->items()->createMany(array_map(fn (OrderLine $line) => [
            'product_id' => $line->productId,
            'product_name' => $line->productName,
            'unit_price_cents' => $line->unitPriceCents,
            'quantity' => $line->quantity,
            'subtotal_cents' => $line->subtotalCents(),
        ], iterator_to_array($lines)));

        return $order;
    }

    /**
     * The order, with its items, only when it belongs to that customer: the order of another
     * customer answers the same as one that does not exist.
     */
    public function findForCustomer(string $customerId, string $orderId): ?Order
    {
        return $this->forCustomer($customerId)->with('items')->find($orderId);
    }

    /**
     * The order with its customer and items, for the admin.
     *
     * @throws ModelNotFoundException
     */
    public function findWithCustomerAndItems(string $id): Order
    {
        return $this->query()->with(['customer', 'items'])->findOrFail($id);
    }

    public function findForPayment(string $customerId, string $orderId): ?OrderForPayment
    {
        $order = $this->forCustomer($customerId)->find($orderId);

        return $order === null ? null : new OrderForPayment($order->id, $order->customer_id, $order->total_cents, $order->status);
    }

    /**
     * Moves the order to a new status only when it is currently in the expected one.
     *
     * The conditional UPDATE makes queued listeners/jobs idempotent: if a job runs twice
     * (retry) or out of order, it simply does nothing.
     */
    public function transitionStatus(Order $order, OrderStatus $from, OrderStatus $to): bool
    {
        $updated = $this->query()
            ->whereKey($order->getKey())
            ->where('status', $from)
            ->update(['status' => $to, 'updated_at' => now()]);

        if ($updated === 1) {
            $order->status = $to;

            return true;
        }

        return false;
    }

    /**
     * Records the estimated delivery day only when the order has none yet.
     *
     * The conditional UPDATE keeps the first date: a retried or repeated DeliveryScheduled does
     * nothing. It does not look at the status, so an order already delivered still gets its date.
     */
    public function recordEstimatedDelivery(Order $order, CarbonInterface $estimatedOn): bool
    {
        $updated = $this->query()
            ->whereKey($order->getKey())
            ->whereNull('estimated_delivery_on')
            ->update(['estimated_delivery_on' => $estimatedOn->toDateString(), 'updated_at' => now()]);

        if ($updated === 1) {
            $order->estimated_delivery_on = $estimatedOn;

            return true;
        }

        return false;
    }

    /** @return Builder<Order> */
    protected function forCustomer(string $customerId): Builder
    {
        return $this->query()->where('customer_id', $customerId);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    protected function newestFirst(Builder $query): Builder
    {
        return $query->latest()->latest('id');
    }
}
