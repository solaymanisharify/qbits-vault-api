<?php

namespace App\Repositories;

use App\Models\CustodianCashHistory;

class CustodianRepository
{

    public function __construct(protected CustodianCashHistory $model) {}


    public function verifyReceivedCash($cashOutId)
    {
        $custodianHistory = $this->model->where('cash_out_id', $cashOutId)->where('status', 'pending')->first();
        if (!$custodianHistory) {
            return errorResponse("Received cash not found", [], 404);
        }
        $custodianHistory->update([
            'status' => 'verified',
            'verified_at' => now()
        ]);

        return successResponse("Successfully verified received cash", $custodianHistory, 200);
    }

    public function getPendingCustodianApprovalsByUserId($userId)
    {
        return $this->model::with(['vault'])
            ->where('custodian_id', $userId)
            ->where('status', 'pending')
            ->get();
    }
}
