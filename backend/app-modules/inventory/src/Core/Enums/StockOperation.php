<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\Enums;

enum StockOperation: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
}
