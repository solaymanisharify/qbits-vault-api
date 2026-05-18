<?php

namespace App\Services;

use App\Models\CustodianCashHistory;
use App\Models\VaultAssign;
use App\Repositories\CashOutBagRepository;
use App\Repositories\CashOutRepository;
use App\Repositories\CashOutRequiredRepository;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class CashOutService
{

    public function __construct(protected CashOutRepository $cashOutRepo, protected CashOutBagRepository $cashOutBagRepo, protected UserService $userService, protected CashOutRequiredRepository $cashOutRequired, protected VaultBagService $vaultBagService) {}

    public function getAll()
    {
        return  $this->cashOutRepo->getAll(request()->only('search'));
    }

    public function find($id)
    {
        return $this->cashOutRepo->find($id);
    }

    public function createCashOut(array $data)
    {

        $data["user_id"] = auth()->id();
        $data["verifier_status"] = "pending";
        $data["status"] = "pending";
        $data["cash_in_id"] = $data["cash_in_id"];

        $vaultId = $data['vault_id'];


        $roles = Role::whereIn('name', ['verifier', 'approver'])->get()->keyBy('name');

        $verifierRole = $roles->get('verifier');
        $approverRole = $roles->get('approver');

        if (!$verifierRole || !$approverRole) {
            $message = match (true) {
                !$verifierRole && !$approverRole => 'Verifier and approver roles not found',
                !$verifierRole                   => 'Verifier role not found',
                !$approverRole                   => 'Approver role not found',
            };

            return errorResponse(['message' => $message], [
                'verifier_role' => (bool) $verifierRole,
                'approver_role' => (bool) $approverRole,
                'role_status' => false,
            ], 500);
        }

        if ($vaultId) {

            $assignments = VaultAssign::where('vault_id', $vaultId)
                ->where('status', 'active')
                ->get(['user_id', 'roles']);


            $verifierUserIds = $assignments
                ->filter(fn($a) => in_array($verifierRole->id, $a->roles ?? []))
                ->pluck('user_id');

            $approverUserIds = $assignments
                ->filter(fn($a) => in_array($approverRole->id, $a->roles ?? []))
                ->pluck('user_id');


            if ($verifierUserIds->isEmpty() || $approverUserIds->isEmpty()) {
                $message = match (true) {
                    $verifierUserIds->isEmpty() && $approverUserIds->isEmpty() => 'Verifier and approver not found for this vault',
                    $verifierUserIds->isEmpty()                                => 'Verifier not found for this vault',
                    $approverUserIds->isEmpty()                                => 'Approver not found for this vault',
                };

                return errorResponse(['message' => $message], [
                    'verifier_found' => !$verifierUserIds->isEmpty(),
                    'approver_found' => !$approverUserIds->isEmpty(),
                    'role_status'    => false,
                ], 500);
            }
        }


        return DB::transaction(function () use ($data, $verifierUserIds, $approverUserIds, $vaultId) {
            $data["tran_id"] = strtoupper(substr(Str::ulid(), 0, 16));

            $cashOut = $this->cashOutRepo->create($data);

            $cashOutId = $cashOut->id;

            if (!empty($data["custodian_id"])) {
                CustodianCashHistory::create([
                    'custodian_id' => $data["custodian_id"],
                    'vault_id' => $vaultId,
                    'cash_out_id' => $cashOutId,
                    'amount' => $data["cash_out_amount"] - $data["request_amount"],
                ]);
            }



            foreach ($data["bags"] as $bag) {
                $cashOutBagData = [
                    'cash_out_id' => $cashOutId,  // Use the stored ID
                    'bags_id' => $bag['bag_id'],
                    'verifier_status' => $data["verifier_status"],
                    'status' => $data["status"],
                ];

                $cashOut = $this->cashOutBagRepo->createCashOutBag($cashOutBagData);
            }


            // Create verifier records
            foreach ($verifierUserIds as $verifier) {
                $this->cashOutRequired->create([
                    'cash_out_id' => $cashOutId,
                    'user_id'    => $verifier,
                ]);
            }

            // Create approver records
            foreach ($approverUserIds as $approver) {
                $this->cashOutRequired->createApprover([
                    'cash_out_id' => $cashOutId,
                    'user_id'    => $approver,
                    // 'type'       => 'approver', // optional
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
    public function getVerifierAllPendingCashOutsByStatus()
    {
        $user = auth()->user();

        $canVerify = $user->can('cash-out.verify');
        $canApprove = $user->can('cash-out.approve');

        // Check if user has at least one required permission
        if (!$canVerify && !$canApprove) {
            return errorResponse('Unauthorized', 403);
        }

        // If user has BOTH permissions, return combined data
        if ($canVerify && $canApprove) {
            // Get cash-ins pending verification by this user
            $pendingVerification = $this->cashOutRepo->getPendingForVerifier($user->id);

            // Get cash-ins pending approval (already verified)
            $pendingApproval = $this->cashOutRepo->getPendingForApprover();

            // Merge both collections and remove duplicates by ID
            $cashOuts = $pendingVerification->merge($pendingApproval)->unique('id');
        }
        // If user has ONLY verify permission
        elseif ($canVerify) {
            $cashOuts = $this->cashOutRepo->getPendingForVerifier($user->id);
        }
        // If user has ONLY approve permission
        else {
            $cashOuts = $this->cashOutRepo->getPendingForApprover();
        }

        return successResponse(
            "Successfully fetched pending cash-ins",
            $cashOuts->load(['requiredVerifiers.user', 'vault', 'cashOutBags.bag']),
            200
        );
    }

    public function approved($cashOutId)
    {

        $user = auth()->user();
        $cashOut = $this->find($cashOutId);


        // Must have permission
        if (!$user->can('cash-out.approve')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }


        // Check if this user is a required verifier for this Cashout
        $requiredApprover = $cashOut->requiredApprovers()
            ->where('user_id', $user->id)
            ->first();


        if (!$requiredApprover) {
            return response()->json(['error' => 'You are not assigned as a approver for this CashOut'], 403);
        }

        if ($requiredApprover->approved) {
            return response()->json(['error' => 'You have already approvered this CashOut'], 400);
        }

        // Mark as verified in required table
        $requiredApprover->update([
            'approved' => true,
            'approved_at' => now(),
        ]);

        // Check if ALL required verifiers have verified
        $totalRequired = $cashOut->requiredApprovers()->count();
        $totalApproved = $cashOut->requiredApprovers()->where('approved', true)->count();


        if ($totalApproved === $totalRequired) {


            $bags = $cashOut->cashOutBags;

            foreach ($bags as $cashOutBag) {

                $bag = $cashOutBag->bag;

                if (!$bag) {
                    continue; // safety check
                }


                $bag->current_amount = 0;
                $bag->denominations = [];

                $bag->last_cash_out_amount = $cashOut->cash_out_amount;
                $bag->last_cash_out_at = now();
                $bag->last_cash_out_by = $cashOut->user_id; // or auth()->id()
                $bag->last_cash_out_tran_id = $cashOut->tran_id;

                // Optional: update statistics
                $bag->total_cash_out_attempts += 1;
                // $bag->total_successful_deposits += 1;

                $bag->save();
            }

            $vault = $cashOut->vault;
            $vault->balance -= $cashOut->cash_out_amount;
            $vault->last_cash_out = now();
            $vault->save();

            $cashOut->status = 'approved';
            $cashOut->save();
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
            'verifier_status' => $cashOut->verifier_status,
            'status' => $cashOut->status,
        ]);
    }
    public function verify($cashOutId)
    {

        $user = auth()->user();
        $cashOut = $this->find($cashOutId);

        if (!$cashOut) {
            return response()->json(['error' => 'CashOut not found'], 404);
        }



        // Must have permission
        if (!$user->can('cash-out.verify')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // $request->validate([
        //     'action' => 'required|in:verify,approve,reject',
        //     'note' => 'nullable|string|max:500',
        // ]);

        // Check if this user is a required verifier for this CashIn
        $requiredVerifier = $cashOut->requiredVerifiers()
            ->where('user_id', $user->id)
            ->first();


        if (!$requiredVerifier) {
            return response()->json(['error' => 'You are not assigned as a verifier for this CashOut'], 403);
        }

        if ($requiredVerifier->verified) {
            return response()->json(['error' => 'You have already verified this CashOut'], 400);
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
        $totalRequired = $cashOut->requiredVerifiers()->count();
        $totalVerified = $cashOut->requiredVerifiers()->where('verified', true)->count();

        if ($totalVerified === $totalRequired) {
            $cashOut->verifier_status = 'verified';
            $cashOut->save();
        }

        // Handle approve/reject (only if user has permission)
        // if ($action === 'approve' && $user->can('cash-in.approve')) {
        //     $cashOut->status = 'approved';
        //     $cashOut->save();
        // } elseif ($action === 'reject' && $user->can('cash-in.reject')) {
        //     $cashOut->status = 'rejected';
        //     $cashOut->save();
        // }

        return response()->json([
            'message' => 'Verified successfully',
            'verifier_status' => $cashOut->verifier_status,
            'status' => $cashOut->status,
        ]);
    }

    public function deleteCashOut($cashOutId)
    {
        $cashOut = $this->find($cashOutId);

        if (!$cashOut) {
            return errorResponse("Cash-out not found", [], 404);
        }

        if ($cashOut->verifier_status !== 'pending' || $cashOut->approver_status !== 'pending') {
            return errorResponse("Only pending cash-out can be deleted", [], 400);
        }


        $cashOut->delete();

        return successResponse("Cash-out deleted successfully", [], 200);
    }
}
