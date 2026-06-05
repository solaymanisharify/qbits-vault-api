<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function index(Request $request)
    {
        info($request->all());
        $timeframe = $request['timeframe'];
        $vaultId = $request['selectedVault'];

        info($timeframe);
        info($vaultId);

        $data = $this->dashboardService->index($timeframe, $vaultId);

        return successResponse("Successfully fetched dashboard data", $data, 200);
    }
}
