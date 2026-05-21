<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AuditConfiguration;
use App\Models\VaultAuditConfig;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ProcessVaultAudits extends Command
{
    protected $signature = 'vault:process-audits';
    protected $description = 'Handles infinite recurring vault audit cycles and notification dispatches.';

    protected $notificationApiUrl = 'https://your-notification-system.com/api/send';

    public function handle()
    {
        $now = Carbon::now();
        $todayDateString = $now->toDateString(); // YYYY-MM-DD
        $currentTimeString = $now->format('H:i');
        $tenMinutesFromNow = $now->copy()->addMinutes(10)->format('H:i');

        $configs = VaultAuditConfig::with('vault')->get();

        foreach ($configs as $config) {
            if (!$config->vault) continue;

            $vaultId = $config->vault_id;
            $vaultName = $config->vault->name;

            // Check if this vault fits the current interval criteria for today
            $isMatchingDay = $this->checkDayMatch($config, $now);

            if ($isMatchingDay) {

                // If a new cycle day has arrived, naturally reset the status from 'completed/missed' back to 'pending'
                if ($config->last_processed_cycle_date !== $todayDateString && $config->status !== 'active') {
                    $config->update([
                        'status' => 'pending'
                    ]);
                }

                // A. 10 Minutes Before Warning Alert
                if ($config->time === $tenMinutesFromNow && $config->status === 'pending') {
                    $this->triggerNotificationApi($vaultId, "Reminder: Recurring scheduled audit for '{$vaultName}' starts in 10 minutes.");
                }

                // B. Exact Start Time Alert -> Open the 6-hour Window
                if ($config->time === $currentTimeString && $config->status === 'pending') {
                    $config->update([
                        'status' => 'active',
                        'current_window_started_at' => $now,
                        'last_processed_cycle_date' => $todayDateString // Lock into today's circle code
                    ]);

                    $this->triggerNotificationApi($vaultId, "Urgent: The recurring audit window for '{$vaultName}' is now active.");
                }
            }

            // C. The 6-Hour Expiration Check (Auto Turn-off active window)
            if ($config->status === 'active' && $config->current_window_started_at) {
                $startedAt = Carbon::parse($config->current_window_started_at);

                if ($now->diffInHours($startedAt) >= 6) {
                    $config->update([
                        'status' => 'missed',
                        'failed_audits' => $config->failed_audits + 1,
                        'current_window_started_at' => null // Turn off timer counter
                    ]);

                    $this->triggerNotificationApi($vaultId, "System Warning: Audit window closed automatically after 6 hours of inactivity. Waiting for next recurring cycle.");
                }
            }
        }
    }

    private function triggerNotificationApi($vaultId, $message)
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.notification_token'),
                'Accept' => 'application/json'
            ])->post($this->notificationApiUrl, [
                'vault_id' => $vaultId,
                'message'  => $message,
                'type'     => 'audit_alert'
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed sending external notification: " . $e->getMessage());
        }
    }

    private function checkDayMatch(VaultAuditConfig $config, Carbon $now): bool
    {
        $dayOfWeekName = $now->format('l'); // "Monday", "Tuesday", etc.

        switch ($config->interval) {
            case 'Daily':
                return true; // Runs every single day

            case 'Weekly':
                return strcasecmp($config->day, $dayOfWeekName) === 0; // Every week on this day

            case 'Bi-Weekly':
                // Runs every second week on this designated day
                return (strcasecmp($config->day, $dayOfWeekName) === 0) && ($now->weekOfYear % 2 === 0);

            case 'Monthly':
                // Runs every month on the first occurrence of this day (Days 1 through 7)
                return strcasecmp($config->day, $dayOfWeekName) === 0 && $now->day <= 7;

            case 'Quarterly':
                // Runs every 3 months (Jan, Apr, Jul, Oct) on the first occurrence of this day
                $startOfQuarterMonths = [1, 4, 7, 10];
                return in_array($now->month, $startOfQuarterMonths) && strcasecmp($config->day, $dayOfWeekName) === 0 && $now->day <= 7;

            default:
                return false;
        }
    }
}
