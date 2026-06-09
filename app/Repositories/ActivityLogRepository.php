<?php

namespace App\Repositories;

use App\Models\ActivityLog;

class ActivityLogRepository
{
    public function index()
    {
        return ActivityLog::query()->orderByDesc('created_at');
    }
}
