<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VaultAuditConfig;
use App\Models\Reconciliation;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ProcessVaultAudits extends Command
{
    protected $signature = 'vault:process-audits';
    protected $description = 'Handles vault audit notifications and reconciliation creation based on next_audit_date.';

    protected $notificationApiUrl = 'https://naas.api.pippasync.com/api/send';

    // Reconciliation statuses that block creating a new one
    protected $blockingStatuses = ['pending', 'counting', 'counted'];

    public function handle()
    {
        $now = Carbon::now(config('app.timezone'));

        $configs = VaultAuditConfig::with('vault')
            ->whereNotNull('next_audit_date')
            ->whereNotNull('vault_id')
            ->get();

        foreach ($configs as $config) {
            if (!$config->vault) continue;

            $vaultId       = $config->vault_id;
            $vaultName     = $config->vault->name;
            $nextAuditDate = Carbon::parse($config->next_audit_date, config('app.timezone'));

            // ─────────────────────────────────────────────
            // 1. NOTIFICATION: 24 hours before next_audit_date
            // ─────────────────────────────────────────────
            if ($this->isWithinWindow($now, $nextAuditDate, hours: 24)) {
                $this->triggerNotificationApi(
                    $vaultId,
                    "Reminder: Scheduled vault audit for '{$vaultName}' is due in 24 hours on {$nextAuditDate->format('D, M d Y h:i A')}."
                );
            }

            // ─────────────────────────────────────────────
            // 2. NOTIFICATION: 6 hours before next_audit_date
            // ─────────────────────────────────────────────
            if ($this->isWithinWindow($now, $nextAuditDate, hours: 6)) {
                $this->triggerNotificationApi(
                    $vaultId,
                    "Urgent: Vault audit for '{$vaultName}' starts in 6 hours at {$nextAuditDate->format('h:i A')}. Ensure everything is ready."
                );
            }

            // ─────────────────────────────────────────────
            // 3. RECONCILE CREATION: 2 days before next_audit_date
            // ─────────────────────────────────────────────
            if ($this->isWithinWindow($now, $nextAuditDate, hours: 48)) {
                $this->handleReconcileCreation($vaultId);
            }
        }
    }


    /**
     * Check if $now is within the exact 10-minute trigger window before the target time.
     * e.g. for hours=24 → fires when now is between 23h50m and 24h00m before nextAuditDate.
     * This prevents repeated triggers on every cron tick.
     */
    private function isWithinWindow(Carbon $now, Carbon $targetDate, int $hours): bool
    {
        $windowStart = (clone $targetDate)->subHours($hours);
        $windowEnd   = (clone $targetDate)->subHours($hours)->addMinutes(10);

        return $now->between($windowStart, $windowEnd);
    }


    /**
     * Create a new reconciliation for the vault only if no blocking status exists.
     * Blocking statuses: pending, counting, counted
     */
    private function handleReconcileCreation(int $vaultId): void
    {
        $exists = Reconciliation::where('vault_id', $vaultId)
            ->whereIn('status', $this->blockingStatuses)
            ->exists();

        if ($exists) {
            \Log::info("Reconcile skipped for vault_id {$vaultId} — active reconciliation already exists.");
            return;
        }

        Reconciliation::create([
            'vault_id' => $vaultId,
            'status'   => 'pending',
        ]);

        \Log::info("New reconciliation created for vault_id {$vaultId}.");
    }


    /**
     * Fire notification to external API.
     */
    private function triggerNotificationApi(int $vaultId, string $message): void
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.notification_token'),
                'Accept'        => 'application/json',
            ])->post($this->notificationApiUrl, [
                'vault_id' => $vaultId,
                'message'  => $message,
                'type'     => 'audit_alert',
            ]);

            \Log::info("Notification sent for vault_id {$vaultId}: {$message}");
        } catch (\Exception $e) {
            \Log::error("Notification failed for vault_id {$vaultId}: " . $e->getMessage());
        }
    }
}
