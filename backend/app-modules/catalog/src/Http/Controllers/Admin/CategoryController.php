<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Catalog\Core\UseCases\CreateCategoryUseCase;
use Modules\Catalog\Core\UseCases\DeleteCategoryUseCase;
use Modules\Catalog\Core\UseCases\UpdateCategoryUseCase;
use Modules\Catalog\Http\Requests\Admin\CategoryRequest;
use Modules\Catalog\Http\Resources\CategoryResource;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;
use Modules\Shared\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function index(CategoryRepository $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->allWithProductCount());
    }

    public function store(CategoryRequest $request, CreateCategoryUseCase $createCategory): JsonResponse
    {
        $category = $createCategory->execute($request->toDto());

        return CategoryResource::make($category)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $category, CategoryRepository $categories): CategoryResource
    {
        return CategoryResource::make($categories->findOrFail($category)->loadCount('products'));
    }

    public function update(CategoryRequest $request, string $category, UpdateCategoryUseCase $updateCategory): CategoryResource
    {
        return CategoryResource::make($updateCategory->execute($category, $request->toDto()));
    }

    public function destroy(string $category, DeleteCategoryUseCase $deleteCategory): Response
    {
        $deleteCategory->execute($category);

        return response()->noContent();
    }
}
