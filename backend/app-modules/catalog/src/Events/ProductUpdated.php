<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

/** Catalog: a product was edited. Recorded in the transaction of the write, with the whole product and its new version. */
final class ProductUpdated extends ProductChanged
{
    public static function eventName(): string
    {
        return 'catalog.product_updated';
    }
}
