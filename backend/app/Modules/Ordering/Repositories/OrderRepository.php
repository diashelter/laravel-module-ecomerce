<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Repositories;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
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
     * Customers without orders are left out: callers default them to zero.
     *
     * @param  list<int>  $customerIds
     * @return array<int, int> [customer_id => total]
     */
    public function countPerCustomer(array $customerIds): array
    {
        return $this->query()
            ->whereIn('user_id', $customerIds)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($total) => (int) $total)
            ->all();
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
     * Creates the order and its items (snapshot of name and price).
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function createWithItems(int $customerId, array $attributes, array $lines): Order
    {
        $order = $this->query()->create([...$attributes, 'user_id' => $customerId]);
        $order->items()->createMany($lines);

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
        return $this->query()->where('user_id', $customerId);
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
