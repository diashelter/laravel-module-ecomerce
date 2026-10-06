<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\CategoryRepository;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: deletes a category without products.
 */
final class DeleteCategoryUseCase
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(Category $category): void
    {
        $this->categoryService->ensureCanBeDeleted($this->categories->hasProducts($category));

        $this->categories->delete($category);
    }
}
