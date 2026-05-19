<?php

namespace App\Http\Controllers;

use App\Services\CashOutService;
use App\Services\CustodianService;
use Illuminate\Http\Request;

class CashOutController extends Controller
{

    public function __construct(protected CashOutService $cashOutService, protected CustodianService $custodianService) {}

    public function index()
    {
        return $this->cashOutService->getAll(request()->only('search', 'user_id'));
    }

    public function show($id)
    {
        return $this->cashOutService->find($id);
    }
    public function store(Request $request)
    {
        return $this->cashOutService->createCashOut($request->all());
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
        return $this->cashOutService->getVerifierAllPendingCashOutsByStatus();
    }
    public function verify($id)
    {
        return $this->cashOutService->verify($id);
    }
    public function approved($id)
    {
        return $this->cashOutService->approved($id);
    }
    public function custodianVerify($id)
    {
        return $this->custodianService->verifyReceivedCash($id);
    }
    public function destroy($id)
    {
        return $this->cashOutService->deleteCashOut($id);
    }
}
