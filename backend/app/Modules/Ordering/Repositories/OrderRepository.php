<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Repositories;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Ordering\ValueObjects\CustomerIds;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Ordering\ValueObjects\OrderCountsByCustomer;
use App\Modules\Ordering\ValueObjects\OrderLine;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ShippingQuote;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Also answers the catalog's ProductOrderHistory contract (bound in OrderingServiceProvider).
 *
 * @extends BaseRepository<Order>
 */
class OrderRepository extends BaseRepository implements ProductOrderHistory
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
    public function paginateForCustomer(int $customerId, int $perPage): LengthAwarePaginator
    {
        return $this->newestFirst($this->forCustomer($customerId))
            ->withCount('items')
            ->paginate($perPage);
    }

    /** @return Collection<int, Order> */
    public function recentForCustomer(int $customerId, int $limit): Collection
    {
        return $this->newestFirst($this->forCustomer($customerId))
            ->withCount('items')
            ->limit($limit)
            ->get();
    }

    public function countForCustomer(int $customerId): int
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
    public function hasBeenOrdered(int $productId): bool
    {
        return OrderItem::query()->where('product_id', $productId)->exists();
    }

    /**
     * Creates the order and its items (snapshot of name and price) together with the copy of the
     * delivery address and the shipping quoted for it. The order total is the one the lines and
     * the shipping calculate, so it cannot diverge from them.
     */
    public function createWithItems(
        int $customerId,
        OrderStatus $status,
        OrderLines $lines,
        DeliveryAddress $address,
        ShippingQuote $shipping,
    ): Order {
        $order = $this->query()->create([
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

    /** @return BaseCollection<string, int> ['YYYY-MM-DD' => total] */
    public function countPerDaySince(Carbon $start): BaseCollection
    {
        return $this->query()
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    /** @return BaseCollection<string, int> ['YYYY-MM' => total] */
    public function countPerMonthSince(Carbon $start): BaseCollection
    {
        return $this->query()
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');
    }

    /** @return Builder<Order> */
    protected function forCustomer(int $customerId): Builder
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
