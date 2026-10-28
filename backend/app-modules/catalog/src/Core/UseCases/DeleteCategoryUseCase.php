<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\Services\CategoryService;
use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: deletes a category without products. catalog.category_deleted carries the stored version plus 1,
 * read under the lock, in the transaction of the delete.
 */
final class DeleteCategoryUseCase
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CategoryService $categoryService,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws BusinessRuleException
     * @throws ModelNotFoundException
     */
    public function execute(string $categoryId): void
    {
        $this->categoryService->ensureCanBeDeleted($this->categories->hasProducts($categoryId));

        DB::transaction(function () use ($categoryId): void {
            $version = $this->categories->lockById($categoryId)->version;

            $this->categories->deleteById($categoryId);

            $this->outbox->record(new CategoryDeleted($categoryId, $version + 1));
        });
    }
}
