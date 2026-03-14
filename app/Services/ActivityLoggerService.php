<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLoggerService
{
    /**
     * Log any event.
     *
     * @param string      $event        e.g. 'created', 'updated', 'deleted', 'cash_in', 'custom'
     * @param string      $module       e.g. 'vault', 'bag', 'transaction'
     * @param string      $description  Human-readable sentence
     * @param array       $options      Extra options:
     *   - subject_type  string   FQCN of model e.g. App\Models\VaultBag
     *   - subject_id    int
     *   - subject_label string   Human label e.g. "SM001"
     *   - old_values    array
     *   - new_values    array
     *   - meta          array    Any extra context
     *   - user_id       int      Override authenticated user
     *   - user_name     string   Override user name
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

        return ActivityLog::create([
            'user_id'       => $userId,
            'user_name'     => $userName,
            'subject_type'  => $options['subject_type']  ?? null,
            'subject_id'    => $options['subject_id']    ?? null,
            'subject_label' => $options['subject_label'] ?? null,
            'event'         => $event,
            'module'        => $module,
            'description'   => $description,
            'old_values'    => $options['old_values']    ?? null,
            'new_values'    => $options['new_values']    ?? null,
            'meta'          => $options['meta']          ?? null,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::userAgent(),
        ]);
    }

    // ── Convenience wrappers ──────────────────────────────────────────────────

    public static function created($model, string $module, string $label, array $newValues = [], array $meta = []): ActivityLog
    {
        return self::log('created', $module, "Created {$label}", [
            'subject_type'  => get_class($model),
            'subject_id'    => $model->id,
            'subject_label' => $label,
            'new_values'    => $newValues ?: $model->toArray(),
            'meta'          => $meta,
        ]);
    }

    public static function updated($model, string $module, string $label, array $oldValues, array $newValues, array $meta = []): ActivityLog
    {
        // Only store changed fields
        $diff = [];
        foreach ($newValues as $key => $val) {
            if (($oldValues[$key] ?? null) != $val) {
                $diff[$key] = ['from' => $oldValues[$key] ?? null, 'to' => $val];
            }
        }

        return self::log('updated', $module, "Updated {$label}", [
            'subject_type'  => get_class($model),
            'subject_id'    => $model->id,
            'subject_label' => $label,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'meta'          => array_merge($meta, ['diff' => $diff]),
        ]);
    }

    public static function deleted($model, string $module, string $label, string $reason = '', array $meta = []): ActivityLog
    {
        return self::log('deleted', $module, "Deleted {$label}" . ($reason ? " — Reason: {$reason}" : ''), [
            'subject_type'  => get_class($model),
            'subject_id'    => $model->id,
            'subject_label' => $label,
            'old_values'    => $model->toArray(),
            'meta'          => array_merge($meta, ['reason' => $reason]),
        ]);
    }

    public static function custom(string $event, string $module, string $description, array $options = []): ActivityLog
    {
        return self::log($event, $module, $description, $options);
    }
}