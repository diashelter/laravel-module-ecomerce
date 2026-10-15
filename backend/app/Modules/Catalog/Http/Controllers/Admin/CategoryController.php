<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\Admin\CategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Repositories\CategoryRepository;
use App\Modules\Catalog\UseCases\CreateCategoryUseCase;
use App\Modules\Catalog\UseCases\DeleteCategoryUseCase;
use App\Modules\Catalog\UseCases\UpdateCategoryUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
