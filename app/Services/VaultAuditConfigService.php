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
    // public function update($data, $id)
    // {
    //     // 1. Fetch targeted config configuration data matrix
    //     $configItem = $this->vaultAuditConfigRepository->find($id);

    //     if ($configItem && $configItem->time) {
    //         $now = Carbon::now();
    //         $interval = strtolower($configItem->interval);
    //         $dayName = strtolower($configItem->day ?? '');
    //         $timeStr = $configItem->time;
    //         $lastAuditDate = $configItem->last_audit_date;

    //         $targetDateTime = null;

    //         switch ($interval) {
    //             case 'daily':
    //                 $targetDateTime = Carbon::parse("today {$timeStr}");
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addDay();
    //                 }
    //                 break;

    //             case 'weekly':
    //                 if (empty($dayName)) break;
    //                 $targetDateTime = Carbon::parse("this {$dayName} {$timeStr}");
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addWeek();
    //                 }
    //                 break;

    //             case 'bi-weekly':
    //             case 'biweekly':
    //                 if (empty($dayName)) break;
    //                 // Base off the last recorded audit date, or fallback safely to now
    //                 $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate) : Carbon::now();
    //                 $targetDateTime = $baseDate->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);

    //                 // Increment forwards by chunks of 2-weeks if baseline is stuck historically
    //                 while ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addWeeks(2);
    //                 }
    //                 break;

    //             case 'monthly':
    //             case 'quarterly':
    //             case 'quaterly':
    //                 if (empty($dayName)) break;
    //                 // Grab the calculated last instance matching day name of this current month
    //                 $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}");

    //                 // If that occurrence has concluded, skip to next target cycle month bounds
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $monthsToAdd = ($interval === 'monthly') ? 1 : 3;
    //                     $futureMonthStr = $now->addMonths($monthsToAdd)->format('F Y');
    //                     $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}");
    //                 }
    //                 break;
    //         }

    //         // 2. Validate calculations and verify lock boundaries
    //         if ($targetDateTime) {
    //             $lockoutStart = (clone $targetDateTime)->subHours(6);

    //             if ($now->between($lockoutStart, $targetDateTime)) {
    //                 return errorResponse(
    //                     "You cannot edit configuration settings within 6 hours of the active audit running interval.",
    //                     422
    //                 );
    //             }
    //         }
    //     }

    //     // 3. Fall through execution when context windows clear successfully
    //     $config = $this->vaultAuditConfigRepository->update($data, $id);
    //     $reconcileData["vault_id"] = $configItem->vault_id;

    //     $this->reconcileService->create($reconcileData);
    //     return successResponse("Successfully updated config", $config, 200);
    // }

    // public function update($data, $id)
    // {

    // info($data);
    //     // 1. Fetch targeted config configuration data matrix
    //     $configItem = $this->vaultAuditConfigRepository->find($id);

    //     if ($configItem && $configItem->time) {
    //         $now = Carbon::now();
    //         $interval = strtolower($configItem->interval);
    //         $dayName = strtolower($configItem->day ?? '');
    //         $timeStr = $configItem->time;
    //         $lastAuditDate = $configItem->last_audit_date;

    //         $targetDateTime = null;

    //         switch ($interval) {
    //             case 'daily':
    //                 $targetDateTime = Carbon::parse("today {$timeStr}");
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addDay();
    //                 }
    //                 break;

    //             case 'weekly':
    //                 if (empty($dayName)) break;
    //                 $targetDateTime = Carbon::parse("this {$dayName} {$timeStr}");
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addWeek();
    //                 }
    //                 break;

    //             case 'bi-weekly':
    //             case 'biweekly':
    //                 if (empty($dayName)) break;
    //                 $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate) : Carbon::now();
    //                 $targetDateTime = $baseDate->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);
    //                 while ($now->isAfter($targetDateTime)) {
    //                     $targetDateTime->addWeeks(2);
    //                 }
    //                 break;

    //             case 'monthly':
    //             case 'quarterly':
    //             case 'quaterly':
    //                 if (empty($dayName)) break;
    //                 $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}");
    //                 if ($now->isAfter($targetDateTime)) {
    //                     $monthsToAdd = ($interval === 'monthly') ? 1 : 3;
    //                     $futureMonthStr = $now->addMonths($monthsToAdd)->format('F Y');
    //                     $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}");
    //                 }
    //                 break;
    //         }

    //         info($targetDateTime);

    //         // 2. Validate calculations and verify lock boundaries
    //         if ($targetDateTime) {
    //             $lockoutStart = (clone $targetDateTime)->subHours(6);

    //             if ($now->between($lockoutStart, $targetDateTime)) {
    //                 return errorResponse(
    //                     "You cannot edit configuration settings within 6 hours of the active audit running interval.",
    //                     422
    //                 );
    //             }
    //         }
    //     }

    //     // 3. Fall through execution when context windows clear successfully
    //     $config = $this->vaultAuditConfigRepository->update($data, $id);

    //     // 4. Reconciliation creation logic based on vault_id
    //     $vaultId = $configItem->vault_id;

    //     if ($vaultId) {
    //         $this->handleReconcileOnConfigUpdate($vaultId, $configItem);
    //     }

    //     return successResponse("Successfully updated config", $config, 200);
    // }

    // private function handleReconcileOnConfigUpdate($vaultId, $configItem): void
    // {
    //     // Find the latest reconciliation record for this vault
    //     $existingReconcile = $this->reconcileService->getPendingReconcileByVaultId($vaultId);


    //     // No existing reconciliation at all → safe to create
    //     if ($existingReconcile) {
    //         return;
    //     }

    //     // No existing reconciliation at all → safe to create
    //     if (!$existingReconcile) {
    //         $this->reconcileService->create(['vault_id' => $vaultId]);
    //         return;
    //     }


    // }

    // private function resolveNextTargetDateTime($configItem): ?Carbon
    // {
    //     if (!$configItem->time) {
    //         return null;
    //     }

    //     $now = Carbon::now();
    //     $interval = strtolower($configItem->interval);
    //     $dayName = strtolower($configItem->day ?? '');
    //     $timeStr = $configItem->time;
    //     $lastAuditDate = $configItem->last_audit_date;

    //     $targetDateTime = null;

    //     switch ($interval) {
    //         case 'daily':
    //             $targetDateTime = Carbon::parse("today {$timeStr}");
    //             if ($now->isAfter($targetDateTime)) {
    //                 $targetDateTime->addDay();
    //             }
    //             break;

    //         case 'weekly':
    //             if (empty($dayName)) break;
    //             $targetDateTime = Carbon::parse("this {$dayName} {$timeStr}");
    //             if ($now->isAfter($targetDateTime)) {
    //                 $targetDateTime->addWeek();
    //             }
    //             break;

    //         case 'bi-weekly':
    //         case 'biweekly':
    //             if (empty($dayName)) break;
    //             $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate) : Carbon::now();
    //             $targetDateTime = $baseDate->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);
    //             while ($now->isAfter($targetDateTime)) {
    //                 $targetDateTime->addWeeks(2);
    //             }
    //             break;

    //         case 'monthly':
    //         case 'quarterly':
    //         case 'quaterly':
    //             if (empty($dayName)) break;
    //             $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}");
    //             if ($now->isAfter($targetDateTime)) {
    //                 $monthsToAdd = ($interval === 'monthly') ? 1 : 3;
    //                 $futureMonthStr = $now->addMonths($monthsToAdd)->format('F Y');
    //                 $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}");
    //             }
    //             break;
    //     }

    //     return $targetDateTime;
    // }

    public function update($data, $id)
    {
        info($data);

        // 1. Fetch targeted config configuration data matrix
        $configItem = $this->vaultAuditConfigRepository->find($id);

        if ($configItem && $configItem->time) {
            $now = Carbon::now();
            $interval = strtolower($configItem->interval);
            $dayName  = strtolower($configItem->day ?? '');
            $timeStr  = $configItem->time;
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
                    $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate) : Carbon::now();
                    $targetDateTime = $baseDate->copy()->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);
                    while ($now->isAfter($targetDateTime)) {
                        $targetDateTime->addWeeks(2);
                    }
                    break;

                case 'monthly':
                    if (empty($dayName)) break;
                    $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}");
                    if ($now->isAfter($targetDateTime)) {
                        $futureMonthStr = $now->copy()->addMonth()->format('F Y');
                        $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}");
                    }
                    break;

                case 'quarterly':
                case 'quaterly':
                    if (empty($dayName)) break;
                    // Find the last Thursday (or given day) of the current quarter-end month
                    $targetDateTime = $this->resolveQuarterlyTarget($dayName, $timeStr, $now);
                    break;
            }

            info($targetDateTime);

            // 2. Validate calculations and verify lock boundaries
            if ($targetDateTime) {
                $lockoutStart = (clone $targetDateTime)->subHours(6);

                if ($now->between($lockoutStart, $targetDateTime)) {
                    return errorResponse(
                        "You cannot edit configuration settings within 6 hours of the active audit running interval.",
                        422
                    );
                }

                // ✅ Attach calculated next_audit_date into the update payload
                $data['next_audit_date'] = $targetDateTime->toDateTimeString();
            }
        }

        // 3. Fall through execution when context windows clear successfully
        $config = $this->vaultAuditConfigRepository->update($data, $id);

        // 4. Reconciliation creation logic based on vault_id
        $vaultId = $configItem->vault_id;

        if ($vaultId) {
            $this->handleReconcileOnConfigUpdate($vaultId, $configItem);
        }

        return successResponse("Successfully updated config", $config, 200);
    }


    /**
     * Resolve quarterly next target:
     * Quarter-end months → March, June, September, December
     * Find the last <dayName> of the current quarter-end month.
     * If already past → jump to the next quarter-end month.
     */



    private function handleReconcileOnConfigUpdate($vaultId, $configItem): void
    {
        // Check if a pending reconciliation already exists for this vault
        $existingReconcile = $this->reconcileService->getPendingReconcileByVaultId($vaultId);

        if ($existingReconcile) {
            // Pending exists → check if we are within the 6-hour lockout window
            $targetDateTime = $this->resolveNextTargetDateTime($configItem);

            if ($targetDateTime) {
                $lockoutStart = (clone $targetDateTime)->subHours(6);
                $now = Carbon::now();

                // Within 6-hour window → skip, the pending one will run soon
                if ($now->between($lockoutStart, $targetDateTime)) {
                    return;
                }
            }

            // Outside 6-hour window but pending exists → still skip to avoid duplicates
            // (remove this return if you want to force a new one outside the window)
            return;
        }

        // No pending reconciliation → safe to create a new one
        $this->reconcileService->create(['vault_id' => $vaultId]);
    }


    private function resolveNextTargetDateTime($configItem): ?Carbon
    {
        if (!$configItem->time) {
            return null;
        }

        $now      = Carbon::now();
        $tz       = config('app.timezone'); // ← grab once, use everywhere
        $interval = strtolower($configItem->interval);
        $dayName  = strtolower($configItem->day ?? '');
        $timeStr  = $configItem->time;
        $lastAuditDate = $configItem->last_audit_date;

        $targetDateTime = null;

        switch ($interval) {
            case 'daily':
                $targetDateTime = Carbon::parse("today {$timeStr}", $tz);
                if ($now->isAfter($targetDateTime)) {
                    $targetDateTime->addDay();
                }
                break;

            case 'weekly':
                if (empty($dayName)) break;
                $targetDateTime = Carbon::parse("this {$dayName} {$timeStr}", $tz);
                if ($now->isAfter($targetDateTime)) {
                    $targetDateTime->addWeek();
                }
                break;

            case 'bi-weekly':
            case 'biweekly':
                if (empty($dayName)) break;
                $baseDate = $lastAuditDate ? Carbon::parse($lastAuditDate, $tz) : Carbon::now($tz);
                $targetDateTime = $baseDate->copy()->addWeeks(2)->next($dayName)->setTimeFromTimeString($timeStr);
                while ($now->isAfter($targetDateTime)) {
                    $targetDateTime->addWeeks(2);
                }
                break;

            case 'monthly':
                if (empty($dayName)) break;
                $targetDateTime = Carbon::parse("last {$dayName} of this month {$timeStr}", $tz);
                if ($now->isAfter($targetDateTime)) {
                    $futureMonthStr = $now->copy()->addMonth()->format('F Y');
                    $targetDateTime = Carbon::parse("last {$dayName} of {$futureMonthStr} {$timeStr}", $tz);
                }
                break;

            case 'quarterly':
            case 'quaterly':
                if (empty($dayName)) break;
                $targetDateTime = $this->resolveQuarterlyTarget($dayName, $timeStr, $now);
                break;
        }

        return $targetDateTime;
    }


    private function resolveQuarterlyTarget(string $dayName, string $timeStr, Carbon $now): Carbon
    {
        $tz = config('app.timezone'); // ← same fix here
        $quarterEndMonths = [3, 6, 9, 12];

        $currentMonth = (int) $now->format('n');
        $targetMonth  = collect($quarterEndMonths)->first(fn($m) => $m >= $currentMonth)
            ?? $quarterEndMonths[0];

        $targetYear = $now->year;
        if ($targetMonth < $currentMonth) {
            $targetYear++;
        }

        $monthStr = Carbon::createFromDate($targetYear, $targetMonth, 1)->format('F Y');
        $targetDateTime = Carbon::parse("last {$dayName} of {$monthStr} {$timeStr}", $tz); // ← fix

        if ($now->isAfter($targetDateTime)) {
            $nextQuarterDate = Carbon::createFromDate($targetYear, $targetMonth, 1)->addMonths(3);
            $monthStr = $nextQuarterDate->format('F Y');
            $targetDateTime = Carbon::parse("last {$dayName} of {$monthStr} {$timeStr}", $tz); // ← fix
        }

        return $targetDateTime;
    }
}
