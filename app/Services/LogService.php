<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use Illuminate\Http\JsonResponse;

class LogService
{

    public function __construct(protected ActivityLogRepository $activityLogRepository) {}

    public function index($request): JsonResponse
    {
        $user = auth()->user();

        $query = $this->activityLogRepository->index();

        $hasGlobalViewAccess = $user->hasRole('super-admin') || $user->hasRole('admin');

        if (!$hasGlobalViewAccess) {

            $query->forUser($user->id);
        } else {

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

        $perPage = min((int) ($request->get('per_page', 25)), 100);
        $logs    = $query->paginate($perPage);


        return successResponse("Successfully fetched logs", $logs, 200);
    }
    public function activityLog(string $event, string $module, $description, $options = [])
    {
        ActivityLoggerService::log(
            $event,
            $module,
            $description,
            $options
        );
    }
}
