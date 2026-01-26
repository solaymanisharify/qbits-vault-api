<?php

namespace App\Services;

use App\Repositories\VaultBagRepository;
use Exception;
use Illuminate\Support\Facades\Log;

class VaultBagService
{
    public function __construct(protected VaultBagRepository $vaultBagRepository) {}
    public function store($data)
    {
        return $this->vaultBagRepository->store($data);
    }

    public function getBagById($request, $id)
    {
        try {

            $amount = $request->query('amount');
            $search = $request->query('search');

            // Fetch bags from repository
            $bags = $this->vaultBagRepository->getBagById($id, $search);


            // Convert to collection and ensure float for current_amount
            $bags = $bags->map(function ($bag) {
                return [
                    'id' => $bag->id,
                    'denominations' => $bag->denominations ?? '',
                    'barcode' => $bag->barcode ?? '',
                    'rack_number' => $bag->rack_number ?? '',
                    'current_amount' => (float) $bag->current_amount,
                ];
            })->values();


            // If no bags found, return empty response
            if ($bags->isEmpty()) {
                Log::warning('No bags found for vault_id', ['vault_id' => $id]);
                return successResponse("No bags found", [], 200);
            }

            // If no amount provided → return all bags (original behavior)
            if (!$amount || !is_numeric($amount) || $amount <= 0) {
    
                return successResponse("Successfully fetched all bags", $bags, 200);
            }

            $target = (float) $amount;

            // Find best match bags
            $result = $this->findBestMatchBags($bags, $target);

            // return $result;

            return successResponse("Successfully fetched all bags", $result, 200);
        } catch (Exception $e) {
            Log::error('Error in getBagById', [
                'vault_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return errorResponse(
                "Failed to fetch bags: " . $e->getMessage(),
                ['exception' => $e->getMessage()],
                500
            );
        }
    }

    private function findBestMatchBags($bags, $target)
    {
        try {

            // Sort bags by amount descending
            $sortedBags = $bags->sortByDesc('current_amount')->values();

            // Step 1: Separate bags into below and above target
            $bagsBelow = $sortedBags->filter(fn($bag) => $bag['current_amount'] <= $target)->values();
            $bagsAbove = $sortedBags->filter(fn($bag) => $bag['current_amount'] > $target)->values();

            // Step 2: Try to build combination from bags below target
            $bestBelowCombination = [];
            $bestBelowTotal = 0;

            if ($bagsBelow->isNotEmpty()) {
                $currentCombination = [];
                $currentTotal = 0;

                foreach ($bagsBelow as $bag) {
                    $newTotal = $currentTotal + $bag['current_amount'];

                    // Keep adding bags while we're below or reasonably close to target
                    if ($newTotal <= $target * 1.1) { // Allow 10% over
                        $currentCombination[] = $bag;
                        $currentTotal = $newTotal;
                    }
                }

                $bestBelowCombination = $currentCombination;
                $bestBelowTotal = $currentTotal;
            }

            // Step 3: Find closest single bag above target
            $closestAbove = $bagsAbove->sortBy(fn($bag) => $bag['current_amount'])->first();

            // Step 4: Decide between combination below vs single above
            $belowDiff = $target - $bestBelowTotal;
            $aboveDiff = $closestAbove ? ($closestAbove['current_amount'] - $target) : PHP_FLOAT_MAX;
            $belowCoverage = $bestBelowTotal / $target;

            // Use combination below if:
            // 1. Coverage is good (≥60%), AND
            // 2. Difference is reasonable (≤40% of target), AND
            // 3. Either no bag above OR below is much closer
            $useBelowCombination = false;

            if ($bestBelowTotal > 0) {
                $reasonableDifference = $belowDiff <= ($target * 0.4);
                $goodCoverage = $belowCoverage >= 0.6;
                $belowIsCloser = $belowDiff < $aboveDiff;

                if ($goodCoverage && $reasonableDifference) {
                    $useBelowCombination = true;
                } else if ($belowIsCloser && $goodCoverage) {
                    $useBelowCombination = true;
                }
            }


            // Step 5: Return final result
            if ($useBelowCombination && !empty($bestBelowCombination)) {
                $finalBags = $bestBelowCombination;
                $finalTotal = $bestBelowTotal;
                $matchType = count($finalBags) > 1 ? 'combination_below' : 'single_below';
            } else if ($closestAbove) {
                $finalBags = [$closestAbove];
                $finalTotal = $closestAbove['current_amount'];
                $matchType = 'single_above';
            } else if (!empty($bestBelowCombination)) {
                // Fallback to below combination
                $finalBags = $bestBelowCombination;
                $finalTotal = $bestBelowTotal;
                $matchType = 'fallback_below';
            } else {
                // Last resort - return all bags
                $finalBags = $sortedBags->toArray();
                $finalTotal = $sortedBags->sum('current_amount');
                $matchType = 'all_bags';
            }

            return $finalBags;

            // return [
            //     // 'target_amount' => $target,
            //     'matched_bags' => $finalBags,
            //     // 'total_matched' => round($finalTotal, 2),
            //     // 'bags_count' => count($finalBags),
            //     // 'match_type' => $matchType,
            //     // 'difference' => round($finalTotal - $target, 2)
            // ];


        } catch (Exception $e) {
            Log::error('Error in findBestMatchBags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function update($data, $id)
    {
        return $this->vaultBagRepository->update($data, $id);
    }
    public function getBagByBagId($id)
    {
        return $this->vaultBagRepository->getBagByBagId($id);
    }
}
