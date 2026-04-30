<?php

namespace App\Http\Controllers;

use App\Services\CashInService;
use Illuminate\Http\Request;

class CashInController extends Controller
{
    protected $cashInService;

    public function __construct(CashInService $cashInService)
    {
        $this->cashInService = $cashInService;
    }

    public function index()
    {
        return $this->cashInService->getAll(request()->only('search', 'user_id'));
    }

    public function store(Request $request)
    {
        return $this->cashInService->createCashIn($request->all());
    }
    public function update(Request $request, $id)
    {
        return $this->cashInService->updateCashIn($request->all(), $id);
    }

    // public function verify(Request $request, $id)
    // {
    //     $request->validate([
    //         'action' => 'required|in:approved,rejected'
    //     ]);

    //     try {
    //         $cashIn = $this->cashInService->verifyCashIn($id, $request->action);
    //         return response()->json([
    //             'message' => 'Cash-in ' . $request->action . ' successfully',
    //             'data'    => $cashIn
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => $e->getMessage()
    //         ], 400);
    //     }
    // }

    // List cash-ins for verifiers
    public function listPending()
    {
        return $this->cashInService->getVerifierAllPendingCashInsByStatus();
    }
    public function verify(Request $request, $id)
    {
        return $this->cashInService->verify($request->all(), $id);
    }
    public function approved(Request $request, $id)
    {
        return $this->cashInService->approved($request->all(), $id);
    }
}
