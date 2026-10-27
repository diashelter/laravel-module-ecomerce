<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

/** Catalog: a category was renamed (also what the rebuild republishes). */
final class CategoryUpdated extends CategoryChanged
{
    public static function eventName(): string
    {
        return 'catalog.category_updated';
    }
}
