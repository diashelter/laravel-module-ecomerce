<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity'])]
#[UseFactory(StockFactory::class)]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasUnits(): bool
    {
        return $this->quantity > 0;
    }
}
