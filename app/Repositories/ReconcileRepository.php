<?php

namespace App\Repositories;

use App\Models\Reconciliation;

class ReconcileRepository
{

    public function index()
    {
        return Reconciliation::where('started_by', auth()->user()->id)->get();
    }
    public function startReconcile($data)
    {
        return Reconciliation::create($data);
    }
}
