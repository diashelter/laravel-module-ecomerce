<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Admin;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Inventory\Core\UseCases\AdjustStockUseCase;
use Modules\Inventory\Http\Requests\Admin\UpdateStockRequest;
use Modules\Inventory\Http\Resources\StockResource;
use Modules\Inventory\Persistence\Queries\StockListQuery;
use Modules\Shared\Http\Controllers\Controller;

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
