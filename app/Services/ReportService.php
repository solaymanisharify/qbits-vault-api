<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Vault;
use App\Models\VaultBag;
use App\Models\CashOutBag;

class ReportService
{
    /**
     * Generate the unified ledger report matrix with filtering and pagination
     *
     * @param \Illuminate\Http\Request|array $request
     * @return array
     */
    public function getLedgerReport($request)
    {
        // 1. Extract query and filter parameters safely
        $vaultId = is_object($request) ? $request->query('vault_id') : ($request['vault_id'] ?? null);
        $timeline = is_object($request) ? $request->query('timeline') : ($request['timeline'] ?? null);
        $tranId = is_object($request) ? $request->query('tran_id') : ($request['tran_id'] ?? null);
        $vaultNameSearch = is_object($request) ? $request->query('vault_name') : ($request['vault_name'] ?? null);

        $perPage = is_object($request) ? $request->query('per_page', 15) : ($request['per_page'] ?? 15);

        // Fetch current authenticated user structural profile
        $user = Auth::user();

        // 2. Base Cash In Query Component (Has a real bag_id)
        $cashInQuery = DB::table('cash_ins')
            ->select(
                'id',
                'tran_id',
                'completed_at',
                'vault_id',
                'bag_id',
                DB::raw('NULL as debit'),
                'cash_in_amount as credit',
                'approver_status',
                DB::raw("'cash_in' as transaction_type")
            )
            ->where('approver_status', 'approved');

        // 3. Base Cash Out Query Component (Uses NULL placeholder for bag_id to match columns)
        $cashOutQuery = DB::table('cash_outs')
            ->select(
                'id',
                'tran_id',
                'completed_at',
                'vault_id',
                DB::raw('NULL as bag_id'),
                'cash_out_amount as debit',
                DB::raw('NULL as credit'),
                'approver_status',
                DB::raw("'cash_out' as transaction_type")
            )
            ->where('approver_status', 'approved');

        // 4. Role-Based Vault Security Filter Validation
        if (!auth()->user()->hasRole('super-admin')) {
            // Pluck explicit vault IDs assigned to the user with an 'active' status condition
            $assignedVaultIds = DB::table('vault_assigns')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('vault_id')
                ->toArray();

            // Enforce scope boundary inside raw query builders execution lifecycle
            $cashInQuery->whereIn('vault_id', $assignedVaultIds);
            $cashOutQuery->whereIn('vault_id', $assignedVaultIds);
        }

        // 5. Inject Core Filter Inputs into Base Subqueries
        if ($vaultId && $vaultId !== 'all') {
            $cashInQuery->where('vault_id', $vaultId);
            $cashOutQuery->where('vault_id', $vaultId);
        }

        if ($timeline === 'today') {
            $today = Carbon::today()->toDateString();
            $cashInQuery->whereDate('completed_at', $today);
            $cashOutQuery->whereDate('completed_at', $today);
        } elseif ($timeline === 'current_month') {
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;

            $cashInQuery->whereMonth('completed_at', $currentMonth)->whereYear('completed_at', $currentYear);
            $cashOutQuery->whereMonth('completed_at', $currentMonth)->whereYear('completed_at', $currentYear);
        }

        // 6. Combine queries using UNION syntax bindings
        $unionSql = $cashInQuery->unionAll($cashOutQuery);

        // 7. Wrap inside subquery context wrapper to handle top-level global operations
        $masterQuery = DB::table(DB::raw("({$unionSql->toSql()}) as combined_ledger"))
            ->mergeBindings($unionSql);

        // Apply Filter/Search parameters safely on the wrapper layer
        if ($tranId) {
            $masterQuery->where('tran_id', 'LIKE', "%{$tranId}%");
        }

        if ($vaultNameSearch) {
            $masterQuery->whereIn('vault_id', function ($query) use ($vaultNameSearch) {
                $query->select('id')
                    ->from('vaults')
                    ->where('name', 'LIKE', "%{$vaultNameSearch}%");
            });
        }

        // 8. Compute total matching summary statistics before pagination slicing
        $allMatchingRows = $masterQuery->get();
        $totalCredits = $allMatchingRows->sum('credit');
        $totalDebits = $allMatchingRows->sum('debit');
        $netBalance = $totalCredits - $totalDebits;

        // 9. Execute sorted database pagination
        $paginatedLedger = $masterQuery->orderBy('completed_at', 'desc')->paginate($perPage);

        // 10. Hydrate relationships dynamically (Batch-loading optimized)
        $itemsCollection = collect($paginatedLedger->items());

        $vaultIds = $itemsCollection->pluck('vault_id')->filter()->unique();
        $vaults = Vault::whereIn('id', $vaultIds)->select('id', 'name')->get()->keyBy('id');

        // Split tracking to resolve structural variations for CashIn vs CashOut bags
        $cashInBagIds = $itemsCollection->where('transaction_type', 'cash_in')->pluck('bag_id')->filter()->unique();
        $cashOutIds = $itemsCollection->where('transaction_type', 'cash_out')->pluck('id')->unique();

        // Fetch CashOut bridge entries using the 'bags_id' pivot column
        $cashOutBagsMap = CashOutBag::whereIn('cash_out_id', $cashOutIds)
            ->select('cash_out_id', 'bags_id')
            ->get()
            ->groupBy('cash_out_id');

        // Merge all unique VaultBag primary keys needed for the query execution
        $cashOutBagIds = $cashOutBagsMap->flatten()->pluck('bags_id');
        $allBagIds = $cashInBagIds->concat($cashOutBagIds)->unique()->filter();

        // Accurate column tracking targeting your barcode property
        $bags = VaultBag::whereIn('id', $allBagIds)
            ->select('id', 'barcode')
            ->get()
            ->keyBy('id');

        // Map the relationship models back onto each row securely
        $finalLedgerItems = $itemsCollection->map(function ($row) use ($vaults, $bags, $cashOutBagsMap) {
            $row->vault = $vaults->get($row->vault_id) ?? null;

            // Re-instantiate unified bags array structure for structural frontend mapping
            $assignedBags = collect();

            if ($row->transaction_type === 'cash_in' && $row->bag_id) {
                if ($bagModel = $bags->get($row->bag_id)) {
                    $assignedBags->push($bagModel);
                }
            } elseif ($row->transaction_type === 'cash_out') {
                $pivotRecords = $cashOutBagsMap->get($row->id) ?? collect();
                foreach ($pivotRecords as $pivot) {
                    if ($bagModel = $bags->get($pivot->bags_id)) {
                        $assignedBags->push($bagModel);
                    }
                }
            }

            $row->assigned_bags = $assignedBags->values()->all();
            return $row;
        });


        $result = [
            'summary' => [
                'total_credits' => (float)$totalCredits,
                'total_debits' => (float)$totalDebits,
                'net_balance' => (float)$netBalance
            ],
            'ledger' => $finalLedgerItems,
            'pagination' => [
                'current_page'  => $paginatedLedger->currentPage(),
                'per_page'      => $paginatedLedger->perPage(),
                'total'         => $paginatedLedger->total(),
                'last_page'     => $paginatedLedger->lastPage(),
                'from'          => $paginatedLedger->firstItem(),
                'to'            => $paginatedLedger->lastItem(),
                'next_page_url' => $paginatedLedger->nextPageUrl(),
                'prev_page_url' => $paginatedLedger->previousPageUrl(),
                'links'         => $paginatedLedger->linkCollection()->toArray(),
            ],
        ];

        return successResponse("Successfully fetched ledger report", $result, 200);
    }
}
