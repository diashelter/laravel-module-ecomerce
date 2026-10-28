<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Queries;

use Generator;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Persistence\Models\Stock;

/** What the rebuild of the storefront republishes from the inventory: every stock row. */
class StockRepublicationQuery
{
    private const PAGE = 200;

    /** @return Generator<int, StockRecord> */
    public function stocks(): Generator
    {
        $after = null;

        while (($page = Stock::query()->when($after, fn ($query, string $id) => $query->where('id', '>', $id))->orderBy('id')->limit(self::PAGE)->get())->isNotEmpty()) {
            foreach ($page as $stock) {
                yield $stock->toRecord();
            }

            $after = $page->last()->id;
        }
    }
}
