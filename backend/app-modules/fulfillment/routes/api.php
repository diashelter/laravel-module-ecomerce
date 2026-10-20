<?php

use Illuminate\Support\Facades\Route;
use Modules\Fulfillment\Http\Controllers\ShippingQuoteController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    Route::get('shipping/quote', [ShippingQuoteController::class, 'show'])->middleware('throttle:60,1,shipping-quote');
});
