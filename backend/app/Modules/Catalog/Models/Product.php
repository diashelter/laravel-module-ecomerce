<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Models\OrderItem;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'price', 'description', 'image_url', 'status'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ProductStatus::class,
        ];
    }

    /** @return HasOne<Stock, $this> */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Catalog status only. Whether the product can be bought (active AND in stock)
     * is decided by PurchaseAvailabilityService.
     */
    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }
}
