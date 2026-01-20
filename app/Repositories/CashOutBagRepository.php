<?php

namespace App\Repositories;

use App\Models\CashOutBag;

class CashOutBagRepository
{
    protected $model;

    public function __construct(CashOutBag $model)
    {
        $this->model = $model;
    }

    public function createCashOutBag($data)
    {
        return $this->model->create($data);
    }
}
