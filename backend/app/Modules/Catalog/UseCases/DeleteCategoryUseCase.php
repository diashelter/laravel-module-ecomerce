<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\Repositories\CategoryRepository;
use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * @throws ModelNotFoundException
     */
    public function execute(string $categoryId): void
    {
        $this->categoryService->ensureCanBeDeleted($this->categories->hasProducts($categoryId));

        $this->categories->deleteById($categoryId);
    }
}
