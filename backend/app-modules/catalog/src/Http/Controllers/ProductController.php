<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Http\Requests\ProductIndexRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Persistence\Queries\StorefrontQuery;
use Modules\Shared\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Public catalog: lists active AND inactive products (the frontend greys out the
     * unavailable ones). Filtering, sorting and pagination happen in the read model (MongoDB).
     */
    public function index(ProductIndexRequest $request, StorefrontQuery $products): AnonymousResourceCollection
    {
        $filters = $request->toDto();

        $page = $products->paginate($filters->category, $filters->sort, config('shop.products_per_page'));

        return ProductResource::collection($page->withQueryString());
    }

    /**
     * The product page is only reachable while the product can be bought.
     */
    public function show(string $product, StorefrontQuery $products): ProductResource
    {
        $product = $products->find($product);

        abort_unless($product->availability()->isAvailable(), 404, 'Produto indisponível.');

        return ProductResource::make($product);
    }
}
