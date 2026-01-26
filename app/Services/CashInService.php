<?php

namespace App\Services;

use App\Repositories\CashInRepository;
use App\Repositories\CashInRequiredRepository;
use Illuminate\Support\Facades\DB;


class CashInService
{

    public function __construct(protected CashInRepository $cashInRepo, protected UserService $userService, protected CashInRequiredRepository $cashInRequired, protected VaultBagService $vaultBagService) {}

    public function getAll()
    {
        return  $this->cashInRepo->getAll(request()->only('search', 'user_id'));
    }

    public function find($id)
    {
        return $this->cashInRepo->find($id);
    }

    public function createCashIn(array $data)
    {
        $data["user_id"] = auth()->id();
        $data["verifier_status"] = "pending";
        $data["status"] = "pending";

        return DB::transaction(function () use ($data) {
            $data["tran_id"] = uniqid();
            $cashIn = $this->cashInRepo->create($data);

            // Get the authenticated user ID
            $authUserId = auth()->id();

            // Get users with 'cash-in.verify' permission
            $verifiers = $this->userService->getAllUsersPermissionByName('cash-in.verify');

            // Get users with 'cash-in.approve' permission
            $approvers = $this->userService->getAllUsersPermissionByName('cash-in.approve');

            // Exclude Super Admin, Admin, and the authenticated user from verifiers
            $verifiers = $verifiers->reject(function ($user) use ($authUserId) {
                return $user->hasRole(['Super Admin']) || $user->id === $authUserId;
            });

            // Exclude Super Admin, Admin, and the authenticated user from approvers
            $approvers = $approvers->reject(function ($user) use ($authUserId) {
                return $user->hasRole(['Super Admin']) || $user->id === $authUserId;
            });

            // Create verifier records
            foreach ($verifiers as $verifier) {
                $this->cashInRequired->create([
                    'cash_in_id' => $cashIn->id,
                    'user_id'    => $verifier->id,
                ]);
            }

            // Create approver records
            foreach ($approvers as $approver) {
                $this->cashInRequired->createApprover([
                    'cash_in_id' => $cashIn->id,
                    'user_id'    => $approver->id,
                ]);
            }

            return successResponse("Successfully created cash-in", [], 200);
        });
    }
    // public function getVerifierAllPendingCashInsByStatus()
    // {

    //     $user = auth()->user();

    //     if ($user->can('cash-in.verify')) {
    //         $cashIns = $this->cashInRepo->getVerifierAllPendingCashInsByStatus(['pending']);
    //     } else if ($user->can('cash-in.approve')) {
    //         $cashIns = $this->cashInRepo->getVerifierAllPendingCashInsByStatus(['approved']);
    //     } else {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     return successResponse("Successfully fetch all pending cash-ins", $cashIns->load('verifications.user'), 200);
    // }

    // public function getVerifierAllPendingCashInsByStatus()
    // {
    //     $user = auth()->user();

    //     // For Verifiers: Show only pending CashIns where this user hasn't verified yet
    //     if ($user->can('cash-in.verify')) {
    //         $cashIns = $this->cashInRepo->getPendingForVerifier($user->id);
    //     }
    //     // For Approvers: Show verified CashIns awaiting approval
    //     elseif ($user->can('cash-in.approve')) {
    //         $cashIns = $this->cashInRepo->getPendingForApprover();
    //     } else {
    //         return errorResponse('Unauthorized', 403);
    //     }

    //     return successResponse(
    //         "Successfully fetched pending cash-ins",
    //         $cashIns->load(['verifications.user', 'requiredVerifiers.user', 'vault']),
    //         200
    //     );
    // }
    public function getVerifierAllPendingCashInsByStatus()
    {
        $user = auth()->user();

        $canVerify = $user->can('cash-in.verify');
        $canApprove = $user->can('cash-in.approve');

        // Check if user has at least one required permission
        if (!$canVerify && !$canApprove) {
            return errorResponse('Unauthorized', 403);
        }

        // If user has BOTH permissions, return combined data
        if ($canVerify && $canApprove) {
            // Get cash-ins pending verification by this user
            $pendingVerification = $this->cashInRepo->getPendingForVerifier($user->id);

            // Get cash-ins pending approval (already verified)
            $pendingApproval = $this->cashInRepo->getPendingForApprover();

            // Merge both collections and remove duplicates by ID
            $cashIns = $pendingVerification->merge($pendingApproval)->unique('id');
        }
        // If user has ONLY verify permission
        elseif ($canVerify) {
            $cashIns = $this->cashInRepo->getPendingForVerifier($user->id);
        }
        // If user has ONLY approve permission
        else {
            $cashIns = $this->cashInRepo->getPendingForApprover();
        }

        return successResponse(
            "Successfully fetched pending cash-ins",
            $cashIns->load(['requiredVerifiers.user', 'vault', 'bags']),
            200
        );
    }

    public function approved($request, $cashInId)
    {
        $user = auth()->user();
        $cashIn = $this->find($cashInId);


        // Must have permission
        if (!$user->can('cash-in.approve')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // $request->validate([
        //     'action' => 'required|in:verify,approve,reject',
        //     'note' => 'nullable|string|max:500',
        // ]);

        // $action = $request["action"];

        // Check if this user is a required verifier for this CashIn
        $requiredApprover = $cashIn->requiredApprovers()
            ->where('user_id', $user->id)
            ->first();


        if (!$requiredApprover) {
            return response()->json(['error' => 'You are not assigned as a approver for this CashIn'], 403);
        }

        if ($requiredApprover->approved) {
            return response()->json(['error' => 'You have already approvered this CashIn'], 400);
        }

        // Log the verification action
        // CashInVerification::create([
        //     'cash_in_id' => $cashIn->id,
        //     'user_id' => $user->id,
        //     'action' => $action,
        //     'note' => $request->note,
        // ]);

        // Mark as verified in required table
        $requiredApprover->update([
            'approved' => true,
            'approved_at' => now(),
        ]);

        // Check if ALL required verifiers have verified
        $totalRequired = $cashIn->requiredApprovers()->count();
        $totalApproved = $cashIn->requiredApprovers()->where('approved', true)->count();

        if ($totalApproved === $totalRequired) {

            $result = handleHttpRequest('POST', env('QBITS_SERVICE_BASE_URL') . '/deposit-orders', [
                'token' => env('QBITS_SERVICE_TOKEN'),
            ], [$cashIn]);


            if ($result['success'] === true) {
                $cashIn->status = 'approved';
                $cashIn->save();

                $bag = $cashIn->bags;

                /// make in cashIns relations bags there update the data amount will be add with old number do it
                if ($bag) {
                    $bag->current_amount += $cashIn->cash_in_amount; // add to existing
                    $bag->last_cash_in_amount = $cashIn->cash_in_amount;
                    $bag->last_cash_in_at = now();
                    $bag->last_cash_in_by = $cashIn->user_id; // or auth()->id()
                    $bag->last_cash_in_tran_id = $cashIn->tran_id;

                    // Merge denominations
                    $oldDenominations = is_string($bag->denominations)
                        ? json_decode($bag->denominations, true)
                        : ($bag->denominations ?? []);

                    $newDenominations = is_string($cashIn->denominations)
                        ? json_decode($cashIn->denominations, true)
                        : ($cashIn->denominations ?? []);

                    $bag->denominations =
                        $mergedDenominations = $oldDenominations;
                    foreach ($newDenominations as $denom => $count) {
                        if (isset($mergedDenominations[$denom])) {
                            $mergedDenominations[$denom] += $count;
                        } else {
                            $mergedDenominations[$denom] = $count;
                        }
                    }


                    $bag->denominations = json_encode($mergedDenominations);

                    // Optional: update statistics
                    $bag->total_cash_in_attempts += 1;
                    $bag->total_successful_deposits += 1;

                    // Optional: add to history
                    // $history = $bag->history ?? [];
                    // $history[] = [
                    //     'action' => 'cash_in',
                    //     'amount' => $cashIn->cash_in_amount,
                    //     'tran_id' => $cashIn->tran_id,
                    //     'by' => $cashIn->user_id,
                    //     'at' => now()->toDateTimeString(),
                    //     'note' => 'Deposited via cash-in approval',
                    // ];
                    // $bag->history = $history;

                    $bag->save();
                }
            }
        }

        // Handle approve/reject (only if user has permission)
        // if ($action === 'approve' && $user->can('cash-in.approve')) {
        //     $cashIn->status = 'approved';
        //     $cashIn->save();
        // } elseif ($action === 'reject' && $user->can('cash-in.reject')) {
        //     $cashIn->status = 'rejected';
        //     $cashIn->save();
        // }

        return response()->json([
            'message' => ' recorded successfully',
            'verifier_status' => $cashIn->verifier_status,
            'status' => $cashIn->status,
        ]);
    }
    public function verify($request, $cashInId)
    {

        $user = auth()->user();
        $cashIn = $this->find($cashInId);

        // Must have permission
        if (!$user->can('cash-in.verify')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // $request->validate([
        //     'action' => 'required|in:verify,approve,reject',
        //     'note' => 'nullable|string|max:500',
        // ]);

        $action = $request["action"];

        // Check if this user is a required verifier for this CashIn
        $requiredVerifier = $cashIn->requiredVerifiers()
            ->where('user_id', $user->id)
            ->first();


        if (!$requiredVerifier) {
            return response()->json(['error' => 'You are not assigned as a verifier for this CashIn'], 403);
        }

        if ($requiredVerifier->verified) {
            return response()->json(['error' => 'You have already verified this CashIn'], 400);
        }

        // Log the verification action
        // CashInVerification::create([
        //     'cash_in_id' => $cashIn->id,
        //     'user_id' => $user->id,
        //     'action' => $action,
        //     'note' => $request->note,
        // ]);

        // Mark as verified in required table
        $requiredVerifier->update([
            'verified' => true,
            'verified_at' => now(),
        ]);

        // Check if ALL required verifiers have verified
        $totalRequired = $cashIn->requiredVerifiers()->count();
        $totalVerified = $cashIn->requiredVerifiers()->where('verified', true)->count();

        if ($totalVerified === $totalRequired) {
            $cashIn->verifier_status = 'verified';
            $cashIn->save();
        }

        // Handle approve/reject (only if user has permission)
        if ($action === 'approve' && $user->can('cash-in.approve')) {
            $cashIn->status = 'approved';
            $cashIn->save();
        } elseif ($action === 'reject' && $user->can('cash-in.reject')) {
            $cashIn->status = 'rejected';
            $cashIn->save();
        }

        return response()->json([
            'message' => ucfirst($action) . ' recorded successfully',
            'verifier_status' => $cashIn->verifier_status,
            'status' => $cashIn->status,
        ]);
    }
}
