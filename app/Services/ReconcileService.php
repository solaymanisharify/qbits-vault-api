<?php

namespace App\Services;

use App\Repositories\ReconcileRepository;

class ReconcileService
{

    public function __construct(protected ReconcileRepository $reconcileRepository) {}

    public function index($request)
    {
        return $this->reconcileRepository->index($request);
    }

    public function startReconcile($data)
    {
        $data['started_by'] = auth()->user()->id;
        $data["scope_id"] = $data["scope_id"];
        $data["reconcile_tran_id"] = $this->generateReconcileId();
        return $this->reconcileRepository->startReconcile($data);
    }

    private function generateReconcileId()
    {
        $prefix = 'REC-';
        $date = date('Ymd'); // Format: 20260119
        $number = str_pad(rand(1, 99999), 4, '0', STR_PAD_LEFT); // 5-digit random number

        return $prefix . $date . $number;
    }
}
