<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Core\ValueObjects\StockView;
use Modules\Inventory\Persistence\Models\Stock;

/**
 * The admin stock list. It reads the `stocks` table and asks the catalog, by id and once for the
 * whole page, for the products: the page size never changes the number of queries.
 */
class StockListQuery
{
    public function __construct(private readonly ProductCatalog $products) {}

    /**
     * A row whose product the catalog no longer has stays in the page, without its product.
     *
     * @return LengthAwarePaginator<int, StockView>
     */
    public function paginateByQuantity(int $perPage): LengthAwarePaginator
    {
        $page = Stock::query()
            ->orderBy('quantity')
            ->orderBy('id')
            ->paginate($perPage);

        $products = $this->products->findMany(new ProductIds(...$page->pluck('product_id')->unique()->values()->all()));

        return $page->through(fn (Stock $stock) => new StockView($stock->toRecord(), $products->find($stock->product_id)));
    }

    /**
     * Completes what an adjustment wrote with its product, without reading `stocks` again.
     */
    public function complete(StockRecord $record): StockView
    {
        return new StockView($record, $this->products->findMany(new ProductIds($record->productId))->find($record->productId));
    }
}
