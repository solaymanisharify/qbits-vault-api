<?php

namespace App\Services;

use App\Repositories\CashInRepository;
use Illuminate\Support\Facades\DB;


class CashInService
{
    protected $cashInRepo;

    public function __construct(CashInRepository $cashInRepo)
    {
        $this->cashInRepo = $cashInRepo;
    }

    public function getAll()
    {
       return  $this->cashInRepo->getAll(request()->only('search', 'user_id'));

    }

    public function createCashIn(array $data)
    {
        $data["user_id"] = auth()->id();
        $data["verifier_status"] = "pending";
        $data["status"] = "pending";

        return DB::transaction(function () use ($data) {
            $this->cashInRepo->create($data);
            return successResponse("Successfully created cash-in", [], 200);
        });
    }

    // public function verifyCashIn($id, string $action) // 'approved' or 'rejected'
    // {
    //     $cashIn = $this->cashInRepo->find($id);

    //     if ($cashIn->verifier_status !== 'pending') {
    //         throw new Exception('This cash-in is already verified.');
    //     }

    //     return DB::transaction(function () use ($id, $action) {
    //         $status = $action === 'approved' ? 'verified' : 'cancelled';

    //         $this->cashInRepo->update($id, [
    //             'verifier_status' => $action,
    //             'status'          => $status,
    //         ]);

    //         return $cashIn->refresh();
    //     });
    // }
}
