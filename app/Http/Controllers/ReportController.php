<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;


class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}


    public function getLedgerReport(Request $request)
    {
        return $this->reportService->getLedgerReport($request->all());
    }
}
