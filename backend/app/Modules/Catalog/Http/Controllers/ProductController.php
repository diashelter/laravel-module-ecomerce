<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\ProductIndexRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Public catalog: lists active AND inactive products (the frontend greys out the
     * unavailable ones). Filtering, sorting and pagination happen in the database.
     */
    public function index(ProductIndexRequest $request, ProductRepository $products): AnonymousResourceCollection
    {
        $filters = $request->toDto();

        $page = $products->paginateCatalog($filters->category, $filters->sort, config('shop.products_per_page'));

        return ProductResource::collection($page->withQueryString());
    }

    /**
     * The product page is only reachable while the product can be bought.
     */
    public function show(Product $product, PurchaseAvailabilityService $availability): ProductResource
    {
        $product->load(['categories', 'stock']);

        abort_unless($availability->isAvailable($product, $product->stock), 404, 'Produto indisponível.');

        return ProductResource::make($product);
    }
}
