<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository
{
    protected function model(): string
    {
        return Category::class;
    }

    /** @return Collection<int, Category> */
    public function allOrderedByName(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function allWithProductCount(): Collection
    {
        return $this->query()->withCount('products')->orderBy('name')->get();
    }

    public function hasProducts(string $categoryId): bool
    {
        // Only the id is needed: the relation query runs on the pivot, without reading the category.
        return (new Category)->forceFill(['id' => $categoryId])->products()->exists();
    }
}
