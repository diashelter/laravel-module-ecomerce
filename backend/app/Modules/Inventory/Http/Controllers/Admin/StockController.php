<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Admin;

use App\Modules\Inventory\Http\Requests\Admin\UpdateStockRequest;
use App\Modules\Inventory\Http\Resources\StockResource;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Inventory\UseCases\AdjustStockUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function index(StockRepository $stocks): AnonymousResourceCollection
    {
        return StockResource::collection($stocks->paginateByQuantity(20));
    }

    public function update(UpdateStockRequest $request, Stock $stock, AdjustStockUseCase $adjustStock): StockResource
    {
        return StockResource::make($adjustStock->execute($stock, $request->toDto()));
    }
}
