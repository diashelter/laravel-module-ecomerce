<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\DTOs\CategoryDTO;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\CategoryRepository;

/**
 * Admin: creates a category (the slug is derived from the name by the model).
 */
final class CreateCategoryUseCase
{
    public function __construct(private readonly CategoryRepository $categories) {}

    public function execute(CategoryDTO $data): Category
    {
        return $this->categories->create($data->toArray())->loadCount('products');
    }
}
