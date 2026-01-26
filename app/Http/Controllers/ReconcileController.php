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

    public function verify(Request $request, $id)
    {
        return $this->reconcileService->verify($request->all(), $id);
    }
    public function approved(Request $request, $id)
    {
        return $this->reconcileService->approved($request->all(), $id);
    }
    public function startReconcile($id)
    {
        return $this->reconcileService->startReconcile($id);
    }

    public function latestReconcile()
    {
        return  $this->reconcileService->latestReconcile();
    }
    public function checkReconcile()
    {
        return  $this->reconcileService->checkReconcile();
    }
    public function completeReconcile(Request $request, $id)
    {
        return  $this->reconcileService->completeReconcile($request->all(), $id);
    }
}
