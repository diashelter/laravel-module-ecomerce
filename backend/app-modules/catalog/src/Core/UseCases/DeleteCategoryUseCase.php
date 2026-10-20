<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Catalog\Core\Services\CategoryService;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

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
