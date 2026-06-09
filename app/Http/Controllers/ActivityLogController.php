<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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

        $history = collect($bag->history ?? [])
            ->sortByDesc('timestamp')
            ->values();

        // Also pull system-wide activity logs for this bag
        $activityLogs = ActivityLog::forSubject(\App\Models\VaultBag::class, $bagId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($log) => [
                'event'       => $log->event,
                'description' => $log->description,
                'data'        => array_merge(
                    $log->meta ?? [],
                    ['old' => $log->old_values, 'new' => $log->new_values]
                ),
                'user_name'   => $log->user_name,
                'timestamp'   => $log->created_at->toIso8601String(),
                'source'      => 'system_log',
            ]);

        // Merge both sources, sorted by timestamp desc
        $merged = collect($history->map(fn($h) => array_merge($h, ['source' => 'bag_history'])))
            ->merge($activityLogs)
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
