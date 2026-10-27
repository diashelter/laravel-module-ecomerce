<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

/** Catalog: a product was activated or deactivated. Recorded in the transaction of the write, with the whole product and its new version. */
final class ProductStatusChanged extends ProductChanged
{
    public static function eventName(): string
    {
        return 'catalog.product_status_changed';
    }
}
