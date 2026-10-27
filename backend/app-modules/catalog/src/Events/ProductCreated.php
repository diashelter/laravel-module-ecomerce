<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

/** Catalog: a product was created. Recorded in the transaction of the write, with the whole product and its new version. */
final class ProductCreated extends ProductChanged
{
    public static function eventName(): string
    {
        return 'catalog.product_created';
    }
}
