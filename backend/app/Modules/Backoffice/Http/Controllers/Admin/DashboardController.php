<?php

declare(strict_types=1);

namespace App\Modules\Backoffice\Http\Controllers\Admin;

use App\Modules\Backoffice\UseCases\GetAdminDashboardUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(GetAdminDashboardUseCase $getDashboard): JsonResponse
    {
        return response()->json(['data' => $getDashboard->execute()]);
    }
}
