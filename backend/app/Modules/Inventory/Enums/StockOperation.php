<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockOperation: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
}
