<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Database\Factories\ProductFactory;

#[Fillable(['name', 'price_cents', 'description', 'image_url', 'status', 'version'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'version' => 'integer',
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
            $this->version,
        );
    }
}
