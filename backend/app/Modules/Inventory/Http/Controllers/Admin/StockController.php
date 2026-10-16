<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Admin;

use App\Modules\Inventory\Http\Requests\Admin\UpdateStockRequest;
use App\Modules\Inventory\Http\Resources\StockResource;
use App\Modules\Inventory\Queries\StockListQuery;
use App\Modules\Inventory\UseCases\AdjustStockUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function index(StockListQuery $stocks): AnonymousResourceCollection
    {
        return StockResource::collection($stocks->paginateByQuantity(20));
    }

    public function update(UpdateStockRequest $request, string $stock, AdjustStockUseCase $adjustStock, StockListQuery $stocks): StockResource
    {
        return StockResource::make($stocks->complete($adjustStock->execute($stock, $request->toDto())));
    }
}
