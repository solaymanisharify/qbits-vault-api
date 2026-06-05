<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLoggerService;
use App\Services\VaultBagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function __construct(protected VaultBagService $vaultBagService, protected ActivityLoggerService $cashInService) {}
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = ActivityLog::query()->orderByDesc('created_at');

        $hasGlobalViewAccess = $user->hasRole('super-admin') || $user->hasRole('admin');

        if (!$hasGlobalViewAccess) {
            // Regular users are strictly hard-locked to their own logs
            $query->forUser($user->id);
        } else {
            // Admins can optionally filter by a targeted user_id if supplied
            if ($userId = $request->user_id) {
                $query->forUser($userId);
            }
        }

        // 2. Structural Dynamic Parameters Filtering
        if ($module = $request->module) {
            $query->where('module', $module);
        }

        if ($event = $request->event) {
            $query->where('event', $event);
        }

        if ($request->filled(['subject_type', 'subject_id'])) {
            $query->forSubject($request->subject_type, $request->subject_id);
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('description',   'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%")
                    ->orWhere('user_name',    'like', "%{$search}%");
            });
        }

        // 3. Performance Optimization: High-efficiency raw index execution instead of whereDate
        if ($from = $request->from) {
            $query->where('created_at', '>=', $from . ' 00:00:00');
        }

        if ($to = $request->to) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        // 4. Bound Pagination Limit Protection Guardrail
        $perPage = min((int) ($request->get('per_page', 25)), 100);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
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
