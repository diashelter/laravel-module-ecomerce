<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Repositories\CategoryRepository;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(CategoryRepository $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->allOrderedByName());
    }
}
