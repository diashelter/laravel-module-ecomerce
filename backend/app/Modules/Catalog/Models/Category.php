<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name'])]
#[UseFactory(CategoryFactory::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // The slug is always derived from the name ("Eletrônicos" -> "eletronicos").
        static::saving(function (Category $category): void {
            $category->slug = self::slugFor($category->name);
        });
    }

    public static function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
