<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\Admin\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateProductStatusRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Queries\ProductQuery;
use App\Modules\Catalog\UseCases\ChangeProductStatusUseCase;
use App\Modules\Catalog\UseCases\CreateProductUseCase;
use App\Modules\Catalog\UseCases\DeleteProductUseCase;
use App\Modules\Catalog\UseCases\UpdateProductUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
