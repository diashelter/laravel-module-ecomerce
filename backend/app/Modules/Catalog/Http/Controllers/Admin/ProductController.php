<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\Admin\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateProductStatusRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
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
    public function index(ProductRepository $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginateForAdmin(15));
    }

    public function store(StoreProductRequest $request, CreateProductUseCase $createProduct): JsonResponse
    {
        $product = $createProduct->execute($request->toDto());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product->load(['categories', 'stock']));
    }

    /**
     * Stock is intentionally not touched here (see StockController).
     */
    public function update(UpdateProductRequest $request, Product $product, UpdateProductUseCase $updateProduct): ProductResource
    {
        return ProductResource::make($updateProduct->execute($product, $request->toDto()));
    }

    public function updateStatus(UpdateProductStatusRequest $request, Product $product, ChangeProductStatusUseCase $changeStatus): ProductResource
    {
        return ProductResource::make($changeStatus->execute($product, $request->toDto()));
    }

    public function destroy(Product $product, DeleteProductUseCase $deleteProduct): Response
    {
        $deleteProduct->execute($product);

        return response()->noContent();
    }
}
