<?php

namespace App\Services;

use App\Models\Vault;
use App\Models\VaultBag;
use App\Models\VaultAssign;
use App\Models\Reconciliation; // Assuming this model maps your reconciliations table
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function index(string $timeframe = '1month', ?int $vaultId = null)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin') || $user->hasRole('super_admin');

        // 1. Get the list of accessible vaults for context partitioning
        if ($isSuperAdmin) {
            $accessibleVaults = Vault::select('id', 'name')->get();
            $assignedVaultIds = [];
        } else {
            $assignedVaultIds = VaultAssign::where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('vault_id')
                ->toArray();

            if (empty($assignedVaultIds)) {
                return $this->emptyDashboardResponse();
            }

            $accessibleVaults = Vault::whereIn('id', $assignedVaultIds)->select('id', 'name')->get();
        }

        // 2. Determine targeted vault IDs for filtering metrics
        $targetVaultIds = $assignedVaultIds;

        if ($vaultId) {
            if (!$isSuperAdmin && !in_array($vaultId, $assignedVaultIds)) {
                return $this->emptyDashboardResponse();
            }
            $targetVaultIds = [$vaultId];
        }

        // 3. Calculate Date Boundaries
        [$currentStart, $prevStart, $prevEnd, $timeframeType] = $this->getTimeframeRanges($timeframe);

        // 4. Base Queries with Vault Filters
        $vaultQuery = Vault::query();
        $bagQuery = VaultBag::query();

        if (!$isSuperAdmin || $vaultId) {
            $vaultQuery->whereIn('id', $targetVaultIds);
            $bagQuery->whereIn('vault_id', $targetVaultIds);
        }

        $totalVaults = $vaultQuery->count();
        $totalBags = $bagQuery->count();

        // 5. Aggregate Financial Totals
        $currentFinancials = (clone $bagQuery)->selectRaw('
            SUM(current_amount) as total_balance,
            SUM(CASE WHEN last_cash_in_amount IS NOT NULL AND updated_at >= ? THEN last_cash_in_amount ELSE 0 END) as total_cash_in,
            SUM(CASE WHEN last_cash_out_amount IS NOT NULL AND updated_at >= ? THEN last_cash_out_amount ELSE 0 END) as total_cash_out
        ', [$currentStart, $currentStart])->first();

        $prevFinancials = (clone $bagQuery)->selectRaw('
            SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN current_amount ELSE 0 END) as total_balance,
            SUM(CASE WHEN last_cash_in_amount IS NOT NULL AND updated_at >= ? AND updated_at <= ? THEN last_cash_in_amount ELSE 0 END) as total_cash_in,
            SUM(CASE WHEN last_cash_out_amount IS NOT NULL AND updated_at >= ? AND updated_at <= ? THEN last_cash_out_amount ELSE 0 END) as total_cash_out
        ', [$prevStart, $prevEnd, $prevStart, $prevEnd, $prevStart, $prevEnd])->first();

        $balanceChange = $this->calculatePercentageChange($currentFinancials->total_balance, $prevFinancials->total_balance);
        $cashInChange = $this->calculatePercentageChange($currentFinancials->total_cash_in, $prevFinancials->total_cash_in);
        $cashOutChange = $this->calculatePercentageChange($currentFinancials->total_cash_out, $prevFinancials->total_cash_out);

        // 6. Generate True Time-Segmented Chart Data
        $chartData = $this->generateTrueChartData($bagQuery, $currentStart, $timeframeType);

        // 7. Get Next Most Reconciliation Schedule Entry
        $nextReconciliation = $this->getNextReconciliationDate($isSuperAdmin, $targetVaultIds);

        // 8. Fetch Real Dynamic Pending Verification Ledgers
        $pendingLedger = $this->getPendingVerificationLedger($user, $isSuperAdmin, $targetVaultIds);

        return [
            'vaults'            => $accessibleVaults,
            'totalVaults'       => $totalVaults,
            'totalBags'         => $totalBags,
            'totalVaultBalance' => [
                'value' => (float) ($currentFinancials->total_balance ?? 0),
                'change' => ($balanceChange >= 0 ? '+' : '') . number_format($balanceChange, 1) . '%',
                'trend' => $balanceChange >= 0 ? 'up' : 'down'
            ],
            'totalCashIn'       => [
                'value' => (float) ($currentFinancials->total_cash_in ?? 0),
                'change' => ($cashInChange >= 0 ? '+' : '') . number_format($cashInChange, 1) . '%',
                'trend' => $cashInChange >= 0 ? 'up' : 'down'
            ],
            'totalCashOut'      => [
                'value' => (float) ($currentFinancials->total_cash_out ?? 0),
                'change' => ($cashOutChange >= 0 ? '+' : '') . number_format($cashOutChange, 1) . '%',
                'trend' => $cashOutChange >= 0 ? 'up' : 'down'
            ],
            'chartData'          => $chartData,
            'nextReconciliation' => $nextReconciliation,
            'pendingLedger'      => $pendingLedger
        ];
    }

    private function getNextReconciliationDate(bool $isSuperAdmin, array $targetVaultIds): ?array
    {
        $query = Reconciliation::where('status', 'pending')
            ->where('from_date', '>=', Carbon::now());

        if (!$isSuperAdmin && !empty($targetVaultIds)) {
            $query->whereIn('vault_id', $targetVaultIds);
        }

        // Fetch nearest upcoming entry
        $upcoming = $query->orderBy('from_date', 'asc')->first();

        if (!$upcoming) {
            return null;
        }

        $scheduledDate = Carbon::parse($upcoming->from_date);
        return [
            'id'           => $upcoming->id,
            'vault_name'   => $upcoming->vault?->name ?? 'Global System',
            // 'next_reconcile_date' => $scheduledDate->toIso8601String(),
            'next_reconcile_date'    => $scheduledDate->format('M d, Y h:m A'),
            'days_remaining' => max(0, (int) Carbon::now()->diffInDays($scheduledDate, false))
        ];
    }

    private function getPendingVerificationLedger($user, bool $isSuperAdmin, array $targetVaultIds): array
    {
        $ledgerItems = collect();

        // =========================================================================
        // 1. COLLECT PENDING CASH IN RECORDS
        // =========================================================================

        // Verifier Pipeline
        $cashInVerifierQuery = \App\Models\CashInRequiredVerifier::where('verified', false);
        if (!$isSuperAdmin) {
            $cashInVerifierQuery->where('user_id', $user->id);
        }

        $pendingInVerifications = $cashInVerifierQuery->with(['cashIn.bags.vault'])
            ->whereHas('cashIn', function ($q) use ($targetVaultIds) {
                if (!empty($targetVaultIds)) {
                    $q->whereIn('vault_id', $targetVaultIds);
                }
            })->get()->map(function ($pivot) {
                return [
                    'tran_id'     => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                    'bag_barcode' => $pivot->cashIn?->bags?->bag_identifier_barcode ?? "BAG-{$pivot->cashIn?->bag_id}",
                    'type'        => 'Cash In',
                    'amount'      => (float) ($pivot->cashIn?->cash_in_amount ?? 0),
                    'status'      => 'Pending Verification',
                    'updated_at'  => $pivot->updated_at,
                    'vault_name'  => $pivot->cashIn?->vault?->name ?? 'Vault Space'
                ];
            });

        // Approver Pipeline
        $cashInApproverQuery = \App\Models\CashInRequiredApprover::where('approved', false);
        if (!$isSuperAdmin) {
            $cashInApproverQuery->where('user_id', $user->id);
        }

        $pendingInApprovals = $cashInApproverQuery->with(['cashIn.bags.vault'])
            ->whereHas('cashIn', function ($q) use ($targetVaultIds) {
                if (!empty($targetVaultIds)) {
                    $q->whereIn('vault_id', $targetVaultIds);
                }
            })->get()->map(function ($pivot) {
                return [
                    'tran_id'     => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                    'bag_barcode' => $pivot->cashIn?->bags?->bag_identifier_barcode ?? "BAG-{$pivot->cashIn?->bag_id}",
                    'type'        => 'Cash In',
                    'amount'      => (float) ($pivot->cashIn?->cash_in_amount ?? 0),
                    'status'      => 'Pending Approval',
                    'updated_at'  => $pivot->updated_at,
                    'vault_name'  => $pivot->cashIn?->vault?->name ?? 'Vault Space'
                ];
            });

        $ledgerItems = $ledgerItems->concat($pendingInVerifications)->concat($pendingInApprovals);

        // =========================================================================
        // 2. COLLECT PENDING CASH OUT RECORDS
        // =========================================================================

        // Verifier Pipeline
        $cashOutVerifierQuery = \App\Models\CashoutRequiredVerifier::where('verified', false);
        if (!$isSuperAdmin) {
            $cashOutVerifierQuery->where('user_id', $user->id);
        }

        $pendingOutVerifications = $cashOutVerifierQuery->with(['cashOut.bags.vault'])
            ->whereHas('cashOut', function ($q) use ($targetVaultIds) {
                if (!empty($targetVaultIds)) {
                    $q->whereIn('vault_id', $targetVaultIds);
                }
            })->get()->map(function ($pivot) {
                return [
                    'tran_id'     => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                    'bag_barcode' => $pivot->cashOut?->bags?->bag_identifier_barcode ?? "BAG-{$pivot->cashOut?->bag_id}",
                    'type'        => 'Cash Out',
                    'amount'      => (float) ($pivot->cashOut?->cash_out_amount ?? 0),
                    'status'      => 'Pending Verification',
                    'updated_at'  => $pivot->updated_at,
                    'vault_name'  => $pivot->cashOut?->vault?->name ?? 'Vault Space'
                ];
            });

        // Approver Pipeline
        $cashOutApproverQuery = \App\Models\CashoutRequiredApprover::where('approved', false);
        if (!$isSuperAdmin) {
            $cashOutApproverQuery->where('user_id', $user->id);
        }

        $pendingOutApprovals = $cashOutApproverQuery->with(['cashOut.bags.vault'])
            ->whereHas('cashOut', function ($q) use ($targetVaultIds) {
                if (!empty($targetVaultIds)) {
                    $q->whereIn('vault_id', $targetVaultIds);
                }
            })->get()->map(function ($pivot) {
                return [
                    'tran_id'     => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                    'bag_barcode' => $pivot->cashOut?->bags?->bag_identifier_barcode ?? "BAG-{$pivot->cashOut?->bag_id}",
                    'type'        => 'Cash Out',
                    'amount'      => (float) ($pivot->cashOut?->cash_out_amount ?? 0),
                    'status'      => 'Pending Approval',
                    'updated_at'  => $pivot->updated_at,
                    'vault_name'  => $pivot->cashOut?->vault?->name ?? 'Vault Space'
                ];
            });

        $ledgerItems = $ledgerItems->concat($pendingOutVerifications)->concat($pendingOutApprovals);

        // =========================================================================
        // 3. DEDUPLICATE BY TRAN_ID & TYPE CONTEXT
        // =========================================================================
        return $ledgerItems
            ->groupBy(function ($item) {
                return $item['type'] . '-' . $item['tran_id'];
            })
            ->map(function ($group) {
                $firstItem = $group->first();

                return [
                    'tran_id'         => $firstItem['tran_id'],
                    'type'       => $firstItem['type'],
                    'amount'     => $firstItem['amount'],
                    'status'     => $firstItem['status'],
                    'time'       => Carbon::parse($firstItem['updated_at'])->diffForHumans(),
                    'vault_name' => $firstItem['vault_name']
                ];
            })
            ->sortByDesc('updated_at')
            ->take(15)
            ->values()
            ->toArray();
    }

    private function getTimeframeRanges(string $timeframe): array
    {
        $now = Carbon::now();
        switch ($timeframe) {
            case '7days':
                return [Carbon::now()->subDays(6)->startOfDay(), $now->subDays(13)->startOfDay(), $now->subDays(7)->endOfDay(), '7days'];
            case '3month':
                return [Carbon::now()->subMonths(2)->startOfMonth(), $now->subMonths(5)->startOfMonth(), $now->subMonths(3)->endOfMonth(), '3month'];
            case '6month':
                return [Carbon::now()->subMonths(5)->startOfMonth(), $now->subMonths(11)->startOfMonth(), $now->subMonths(6)->endOfMonth(), '6month'];
            case '1year':
                return [Carbon::now()->subMonths(11)->startOfMonth(), $now->subMonths(23)->startOfMonth(), $now->subMonths(12)->endOfMonth(), '1year'];
            case '1month':
            default:
                return [Carbon::now()->subDays(27)->startOfDay(), $now->subDays(55)->startOfDay(), $now->subDays(28)->endOfDay(), '1month'];
        }
    }

    private function calculatePercentageChange($current, $previous): float
    {
        if (!$previous || $previous == 0) return $current > 0 ? 100.0 : 0.0;
        return (($current - $previous) / $previous) * 100;
    }

    private function generateTrueChartData($bagQuery, Carbon $startDate, string $timeframeType): array
    {
        $chartData = [];
        $now = Carbon::now();

        $buckets = [];
        if ($timeframeType === '7days') {
            for ($i = 0; $i < 7; $i++) {
                $date = (clone $startDate)->addDays($i);
                $buckets[$date->format('Y-m-d')] = ['name' => $date->format('D'), 'start' => (clone $date)->startOfDay(), 'end' => (clone $date)->endOfDay()];
            }
        } elseif ($timeframeType === '1month') {
            for ($i = 0; $i < 4; $i++) {
                $startW = (clone $startDate)->addWeeks($i)->startOfDay();
                $endW = ($i == 3) ? (clone $now)->endOfDay() : (clone $startW)->addDays(6)->endOfDay();
                $buckets[$i] = ['name' => 'Week ' . ($i + 1), 'start' => $startW, 'end' => $endW];
            }
        } else {
            $monthsCount = $timeframeType === '3month' ? 3 : ($timeframeType === '6month' ? 6 : 12);
            for ($i = 0; $i < $monthsCount; $i++) {
                $date = (clone $startDate)->addMonths($i);
                $buckets[$date->format('Y-m')] = ['name' => $date->format('M'), 'start' => (clone $date)->startOfMonth(), 'end' => (clone $date)->endOfMonth()];
            }
        }

        $bags = $bagQuery->where('updated_at', '>=', $startDate)->get();

        foreach ($buckets as $key => $bucket) {
            $cashInSum = 0;
            $cashOutSum = 0;
            $reconciliationSum = 0;

            foreach ($bags as $bag) {
                $recordTimestamp = Carbon::parse($bag->updated_at);

                if ($recordTimestamp->between($bucket['start'], $bucket['end'])) {
                    if ($bag->last_cash_in_amount !== null) {
                        $cashInSum += (float) $bag->last_cash_in_amount;
                    }
                    if ($bag->last_cash_out_amount !== null) {
                        $cashOutSum += (float) $bag->last_cash_out_amount;
                    }
                    if ($bag->status === 'complete' || $bag->status === 'completed') {
                        $reconciliationSum += max(0, (float)$bag->last_cash_in_amount - (float)$bag->last_cash_out_amount);
                    }
                }
            }

            $chartData[] = [
                'name'           => $bucket['name'],
                'cashIn'         => $cashInSum,
                'cashOut'        => $cashOutSum,
                'reconciliation' => $reconciliationSum
            ];
        }

        return $chartData;
    }

    private function emptyDashboardResponse(): array
    {
        return [
            'vaults' => [],
            'totalVaults' => 0,
            'totalBags' => 0,
            'totalVaultBalance' => ['value' => 0, 'change' => '0%', 'trend' => 'up'],
            'totalCashIn' => ['value' => 0, 'change' => '0%', 'trend' => 'up'],
            'totalCashOut' => ['value' => 0, 'change' => '0%', 'trend' => 'down'],
            'chartData' => [],
            'nextReconciliation' => null,
            'pendingLedger' => []
        ];
    }
}
