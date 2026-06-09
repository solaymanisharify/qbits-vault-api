<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Log any application event automatically calculating models mutations.
     *
     * @param string      $event        e.g., 'created', 'updated', 'deleted', 'cash_in', 'reconciled'
     * @param string      $module       e.g., 'role', 'vault_management', 'user_management'
     * @param string      $description  The explicit human-readable description sentence
     * @param array       $options      Contextual payload arrays:
     * - model         object   The Eloquent model instance involved
     * - label         string   Readable fallback marker if model object isn't parsed
     * - old_values    array    Snapshot before action
     * - new_values    array    Snapshot after action
     * - meta          array    Extra payload context variables
     * - user_id       int      Override authenticated runner ID
     * - user_name     string   Override runner name string
     */
    public static function log(
        string $event,
        string $module,
        string $description,
        array  $options = []
    ): ActivityLog {
        $user     = Auth::user();
        $userId   = $options['user_id']   ?? $user?->id;
        $userName = $options['user_name'] ?? $user?->name ?? 'System';

        // 1. Unpack model parameters automatically if provided
        $model        = $options['model'] ?? null;
        $subjectType  = $model ? get_class($model) : null;
        $subjectId    = $model ? $model->id : ($options['subject_id'] ?? null);
        $subjectLabel = $options['label'] ?? ($options['subject_label'] ?? null);

        $oldValues    = $options['old_values'] ?? null;
        $newValues    = $options['new_values'] ?? null;
        $meta         = $options['meta'] ?? [];

        // 2. Automatically capture dataset states if missing based on event
        if ($model && !$newValues && in_array($event, ['created', 'updated'])) {
            $newValues = $model->toArray();
        }
        if ($model && !$oldValues && $event === 'deleted') {
            $oldValues = $model->toArray();
        }

        // 3. Automatically calculate differences ('diff') if an update occurred
        if (!empty($oldValues) && !empty($newValues) && $event === 'updated') {
            $diff = [];
            foreach ($newValues as $key => $val) {
                if (($oldValues[$key] ?? null) != $val) {
                    $diff[$key] = ['from' => $oldValues[$key] ?? null, 'to' => $val];
                }
            }
            if (!empty($diff)) {
                $meta['diff'] = $diff;
            }
        }

        // 4. Sanitize and filter passwords or credentials before saving to audit history
        $oldValues = $oldValues ? self::sanitize($oldValues) : null;
        $newValues = $newValues ? self::sanitize($newValues) : null;
        $meta      = !empty($meta) ? self::sanitize($meta) : null;

        return ActivityLog::create([
            'user_id'       => $userId,
            'user_name'     => $userName,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'subject_label' => $subjectLabel,
            'event'         => $event,
            'module'        => $module,
            'description'   => $description,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'meta'          => $meta,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::userAgent(),
        ]);
    }

    /**
     * Sanitizes data structures removing sensitive values from database logs.
     */
    private static function sanitize(array $data): array
    {
        $hiddenKeys = ['password', 'password_confirmation', 'pin', 'token', 'cvv'];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $hiddenKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }
        return $data;
    }
}
