<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Http\Resources\CategoryResource;
use Modules\Catalog\Persistence\Repositories\CategoryRepository;
use Modules\Shared\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function index(CategoryRepository $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->allOrderedByName());
    }
}
