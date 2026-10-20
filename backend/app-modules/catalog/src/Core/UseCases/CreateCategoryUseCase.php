<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Modules\Catalog\Core\DTOs\CategoryDTO;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;

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
