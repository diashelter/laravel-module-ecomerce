<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\ValueObjects\ProductRecord;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'price_cents', 'description', 'image_url', 'status'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** The data of this row, for the callers that must not hold the model. */
    public function toRecord(): ProductRecord
    {
        return new ProductRecord(
            $this->id,
            $this->name,
            $this->price_cents,
            $this->description,
            $this->image_url,
            $this->status,
            $this->created_at?->toImmutable(),
            $this->updated_at?->toImmutable(),
        );
    }
}
