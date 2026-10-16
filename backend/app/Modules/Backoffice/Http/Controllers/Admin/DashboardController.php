<?php

declare(strict_types=1);

namespace App\Modules\Backoffice\Http\Controllers\Admin;

use App\Modules\Backoffice\Queries\DashboardQuery;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardQuery $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->get()]);
    }
}
