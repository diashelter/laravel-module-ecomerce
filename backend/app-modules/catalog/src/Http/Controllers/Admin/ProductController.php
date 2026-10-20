<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Catalog\Core\UseCases\ChangeProductStatusUseCase;
use Modules\Catalog\Core\UseCases\CreateProductUseCase;
use Modules\Catalog\Core\UseCases\DeleteProductUseCase;
use Modules\Catalog\Core\UseCases\UpdateProductUseCase;
use Modules\Catalog\Http\Requests\Admin\StoreProductRequest;
use Modules\Catalog\Http\Requests\Admin\UpdateProductRequest;
use Modules\Catalog\Http\Requests\Admin\UpdateProductStatusRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Persistence\Queries\ProductQuery;
use Modules\Shared\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index(ProductQuery $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginateForAdmin(15));
    }

    public function store(StoreProductRequest $request, CreateProductUseCase $createProduct, ProductQuery $products): JsonResponse
    {
        $product = $createProduct->execute($request->toDto());

        return ProductResource::make($products->complete($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $product, ProductQuery $products): ProductResource
    {
        return ProductResource::make($products->find($product));
    }

    /**
     * Stock is intentionally not touched here (see StockController).
     */
    public function update(UpdateProductRequest $request, string $product, UpdateProductUseCase $updateProduct, ProductQuery $products): ProductResource
    {
        return ProductResource::make($products->complete($updateProduct->execute($product, $request->toDto())));
    }

    public function updateStatus(UpdateProductStatusRequest $request, string $product, ChangeProductStatusUseCase $changeStatus, ProductQuery $products): ProductResource
    {
        return ProductResource::make($products->complete($changeStatus->execute($product, $request->toDto())));
    }

    public function destroy(string $product, DeleteProductUseCase $deleteProduct): Response
    {
        $deleteProduct->execute($product);

        return response()->noContent();
    }
}
