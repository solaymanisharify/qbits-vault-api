<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultBag;

class DashboardService
{

    public function index()
    {
        // count total vaults
        $totalVaults = Vault::count();
        // count total bags
        $totalBags = VaultBag::count();

        // sum total vault balance
        $totalVaultBalance = VaultBag::sum('current_amount');

        // total cashin
        $totalCashIn = VaultBag::sum('last_cash_in_amount');

        //total cashout amount
        $totalCashOut = VaultBag::sum('last_cash_out_amount');

        // get last cashin amount and date from vault bags
        $lastCashIn = VaultBag::orderBy('last_cash_in_at', 'desc')->first();
        $lastCashInAmount = $lastCashIn ? $lastCashIn->last_cash_in_amount : 0;

        // get last cashout amount and date from vault bags
        $lastCashOut = VaultBag::orderBy('last_cash_out_at', 'desc')->first();
        $lastCashOutAmount = $lastCashOut ? $lastCashOut->last_cash_out_amount : 0;

        return [
            'totalVaults' => $totalVaults,
            'totalBags' => $totalBags,
            'totalVaultBalance' => $totalVaultBalance,
            'totalCashIn' => $totalCashIn,
            'totalCashOut' => $totalCashOut,
            'lastCashInAmount' => $lastCashInAmount,
            'lastCashOutAmount' => $lastCashOutAmount,
        ];
    }
}
