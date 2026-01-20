<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function index()
    {
        $data = $this->dashboardService->index();

        return successResponse("Successfully fetched dashboard data", $data, 200);
    }
}
