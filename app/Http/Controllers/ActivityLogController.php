<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    /**
     * GET /api/activity-logs
     *
     * Query params:
     *   module        string   filter by module (vault, bag, transaction…)
     *   event         string   filter by event  (created, updated, deleted…)
     *   subject_type  string   e.g. App\Models\VaultBag
     *   subject_id    int
     *   user_id       int
     *   search        string   searches description + subject_label + user_name
     *   from          date     YYYY-MM-DD
     *   to            date     YYYY-MM-DD
     *   per_page      int      default 25
     */
    public function index(Request $request): JsonResponse
    {

        $query = ActivityLog::query()->orderByDesc('created_at');

        if ($module = $request->module) {
            $query->where('module', $module);
        }

        if ($event = $request->event) {
            $query->where('event', $event);
        }

        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $query->forSubject($request->subject_type, $request->subject_id);
        }

        if ($userId = $request->user_id) {
            $query->forUser($userId);
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('description',   'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%")
                    ->orWhere('user_name',    'like', "%{$search}%");
            });
        }

        if ($from = $request->from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = min((int) ($request->per_page ?? 25), 100);
        $logs    = $query->paginate($perPage);

        info($logs);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * GET /api/activity-logs/for-bag/{bagId}
     * Returns the internal per-bag history array stored in vault_bags.history
     */
    public function BagHistory(int $bagId): JsonResponse
    {
        $bag = \App\Models\VaultBag::withTrashed()->findOrFail($bagId);

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
