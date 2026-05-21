<?php

namespace App\Services;

use App\Repositories\VaultAuditConfigRepository;
use Carbon\Carbon;

class VaultAuditConfigService
{
    public function __construct(protected VaultAuditConfigRepository $vaultAuditConfigRepository, protected ReconcileService $reconcileService) {}

    public function getAll($request)
    {
        return $this->vaultAuditConfigRepository->getAll($request);
    }

    public function create($data)
    {
        return $this->vaultAuditConfigRepository->create($data);
    }
    public function update($data, $id)
    {
        // 1. Fetch targeted config configuration data matrix
        $configItem = $this->vaultAuditConfigRepository->find($id);

        if ($configItem && $configItem->time) {
            $now = Carbon::now();
            $interval = strtolower($configItem->interval);
            $dayName = strtolower($configItem->day ?? '');
            $timeStr = $configItem->time;
            $lastAuditDate = $configItem->last_audit_date;

            $targetDateTime = null;

            switch ($interval) {
                case 'daily':
                    $targetDateTime = Carbon::parse("today {$timeStr}");
                    if ($now->isAfter($targetDateTime)) {
                        $targetDateTime->addDay();
                    }
                    break;

                case 'weekly':
                    if (empty($dayName)) break;
                    $targetDateTime = Carbon::parse("this {$dayName} {$timeStr}");
                    if ($now->isAfter($targetDateTime)) {
                        $targetDateTime->addWeek();
                    }
                    break;

                case 'bi-weekly':
                case 'biweekly':
                    if (empty($dayName)) break;
                    // Base off the last recorded audit date, or fallback safely to now
                    $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate) : Carbon::now();
                    $targetDateTime = $baseDate->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);

                    // Increment forwards by chunks of 2-weeks if baseline is stuck historically
                    while ($now->isAfter($targetDateTime)) {
                        $targetDateTime->addWeeks(2);
                    }
                    break;

                case 'monthly':
                case 'quarterly':
                case 'quaterly':
                    if (empty($dayName)) break;
                    // Grab the calculated last instance matching day name of this current month
                    $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}");

                    // If that occurrence has concluded, skip to next target cycle month bounds
                    if ($now->isAfter($targetDateTime)) {
                        $monthsToAdd = ($interval === 'monthly') ? 1 : 3;
                        $futureMonthStr = $now->addMonths($monthsToAdd)->format('F Y');
                        $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}");
                    }
                    break;
            }

            // 2. Validate calculations and verify lock boundaries
            if ($targetDateTime) {
                $lockoutStart = (clone $targetDateTime)->subHours(6);

                if ($now->between($lockoutStart, $targetDateTime)) {
                    return errorResponse(
                        "You cannot edit configuration settings within 6 hours of the active audit running interval.",
                        422
                    );
                }
            }
        }

        // 3. Fall through execution when context windows clear successfully
        $config = $this->vaultAuditConfigRepository->update($data, $id);
        $reconcileData["vault_id"] = $configItem->vault_id;

        $this->reconcileService->create($reconcileData);
        return successResponse("Successfully updated config", $config, 200);
    }
}
