<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

/** Catalog: a category was created. */
final class CategoryCreated extends CategoryChanged
{
    public static function eventName(): string
    {
        return 'catalog.category_created';
    }
}
