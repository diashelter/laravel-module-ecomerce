<?php

declare(strict_types=1);

namespace Modules\Backoffice\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Modules\Backoffice\Persistence\Queries\DashboardQuery;
use Modules\Shared\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke(DashboardQuery $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->get()]);
    }
}
