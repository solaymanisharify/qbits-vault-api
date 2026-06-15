<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Services\ActivityLoggerService;
use App\Services\LogService;
use App\Services\VaultBagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function __construct(protected VaultBagService $vaultBagService, protected ActivityLoggerService $cashInService, protected LogService $logService) {}
    public function index(Request $request)
    {
        return  $this->logService->index($request);
    }

    public function BagHistory(int $bagId): JsonResponse
    {
        $bag = $this->vaultBagService->findVaultbagWithTrashedByBagId($bagId);

        // Cash-in transactions for this bag
        $cashIns = CashIn::with('user')
            ->where('bag_id', $bagId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'event'       => 'Cash In',
                'description' => '৳' . number_format($c->cash_in_amount, 2) . ' deposited — TXN: ' . $c->tran_id,
                'user_name'   => $c->user?->name ?? 'N/A',
                'timestamp'   => $c->created_at->toIso8601String(),
                'status'      => $c->approver_status ?? $c->verifier_status ?? 'pending',
                'source'      => 'cash_in',
            ]);

        // Cash-out transactions for this bag (via cash_in_id → cash_in.bag_id)
        $cashInIds = CashIn::where('bag_id', $bagId)->pluck('id');
        $cashOuts = CashOut::with('user')
            ->whereIn('cash_in_id', $cashInIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'event'       => 'Cash Out',
                'description' => '৳' . number_format($c->cash_out_amount, 2) . ' withdrawn — TXN: ' . $c->tran_id,
                'user_name'   => $c->user?->name ?? 'N/A',
                'timestamp'   => $c->created_at->toIso8601String(),
                'status'      => $c->approver_status ?? $c->verifier_status ?? 'pending',
                'source'      => 'cash_out',
            ]);

        $merged = $cashIns->merge($cashOuts)
            ->sortByDesc('timestamp')
            ->values();

        return response()->json([
            'success' => true,
            'bag_id'  => $bagId,
            'barcode' => $bag->barcode,
            'history' => $merged,
        ]);
    }

    /**
     * POST /api/activity-logs/custom
     * Create a manual/custom log entry from the frontend or other services
     */
    public function custom(Request $request): JsonResponse
    {
        $request->validate([
            'event'       => 'required|string',
            'module'      => 'required|string',
            'description' => 'required|string',
        ]);

        $log = ActivityLoggerService::custom(
            $request->event,
            $request->module,
            $request->description,
            $request->only(['subject_type', 'subject_id', 'subject_label', 'meta'])
        );

        return response()->json(['success' => true, 'data' => $log]);
    }
}
