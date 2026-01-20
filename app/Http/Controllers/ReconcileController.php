<?php

namespace App\Http\Controllers;

use App\Services\ReconcileService;
use Illuminate\Http\Request;

class ReconcileController extends Controller
{
    public function __construct(protected ReconcileService $reconcileService) {}

    public function index(Request $request)
    {
        $data = $this->reconcileService->index($request->all());

        return successResponse("Successfully fetched reconciles", $data, 200);
    }

    public function startReconcile(Request $request)
    {
        return $this->reconcileService->startReconcile($request->all());
    }
}
