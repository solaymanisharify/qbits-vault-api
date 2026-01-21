<?php

namespace App\Http\Controllers;

use App\Services\ReconcileService;
use Illuminate\Http\Request;

class ReconcileController extends Controller
{
    public function __construct(protected ReconcileService $reconcileService) {}

    public function index(Request $request)
    {
        return  $this->reconcileService->index($request->all());
    }

    public function create(Request $request)
    {
        return $this->reconcileService->create($request->all());
    }
    public function listPending()
    {
        return $this->reconcileService->getVerifierAllPendingReconcilesByStatus();
    }
}
