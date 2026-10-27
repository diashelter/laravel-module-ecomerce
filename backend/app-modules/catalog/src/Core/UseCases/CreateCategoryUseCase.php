<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\CategoryDTO;
use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * Admin: creates a category (the slug is derived from the name by the model) at version 1, and records
 * catalog.category_created in the same transaction.
 */
final class CreateCategoryUseCase
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(CategoryDTO $data): Category
    {
        $category = DB::transaction(function () use ($data): Category {
            $category = $this->categories->create([...$data->toArray(), 'version' => 1]);

            $this->outbox->record(new CategoryCreated($category->id, $category->name, $category->slug, 1));

            return $category;
        });

        return $category->loadCount('products');
    }
}
