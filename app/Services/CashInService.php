<?php

namespace App\Services;

use App\Models\CashIn;
use App\Models\VaultAssign;
use App\Models\VaultBag;
use App\Repositories\CashInRepository;
use App\Repositories\CashInRequiredRepository;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;


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

        $orderIds = collect($data['orders'])->pluck('order_id');

        $existingOrders = CashIn::where(function ($query) use ($orderIds) {
            foreach ($orderIds as $orderId) {
                $query->orWhereJsonContains('orders', ['order_id' => $orderId]);
            }
        })->pluck('orders');

        $duplicates = $existingOrders
            ->flatten(1)
            ->pluck('order_id')
            ->intersect($orderIds)
            ->values();

        if ($duplicates->isNotEmpty()) {
            return errorResponse(
                ['message' => 'Orders ' . $duplicates->implode(', ') . ' are already linked to a cash-in'],
                [],
                500
            );
        }


        $data["user_id"] = auth()->id();
        $data["verifier_status"] = "pending";
        $data["status"] = "pending";
        $vaultId = $data['vault_id'];

        $authUserId = auth()->user();

        // $data["vault_id"] = $vaultId;
        $cashInAmount = $data['cash_in_amount'];

        $bagAmountLimit = 200000;

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

        // calculation which bag is suitable for the cash-in amount
        $bag = VaultBag::where('vault_id', $vaultId)
            ->where('is_active', true)
            ->where('current_amount', 0)
            ->first();


        if (!$bag) {

            $role = $authUserId->roles->contains(function ($role) {
                return strtolower($role->name) === 'bag create';
            });

            return errorResponse(
                [
                    'message' => 'No bag available for cash-in',
                    'bag_create_role' => $role,
                    'vault_id' => $vaultId,
                ],
                [],
                500
            );
        }

        $data['bag_id'] = $bag->id;


        return DB::transaction(function () use ($data, $verifierUserIds, $approverUserIds) {
            $data["tran_id"] = strtoupper(substr(Str::ulid(), 0, 16));
            // find the users who are verifier and approver and assign to the cash-in
            $cashIn = $this->cashInRepo->create($data);

            // Create verifier records
            foreach ($verifierUserIds as $verifier) {
                $this->cashInRequired->create([
                    'cash_in_id' => $cashIn->id,
                    'user_id'    => $verifier,
                ]);
            }

            // Create approver records
            foreach ($approverUserIds as $approver) {
                $this->cashInRequired->createApprover([
                    'cash_in_id' => $cashIn->id,
                    'user_id'    => $approver,
                ]);
            }

            return successResponse("Successfully created cash-in", $cashIn, 200);
        });
    }

    public function updateCashIn($data, $id)
    {
        $cashIn = $this->find($id);

        if (!$cashIn) {
            return errorResponse("Cash-in not found", [], 404);
        }

        if ($cashIn->verifier_status !== 'pending' || $cashIn->approver_status !== 'pending') {
            return errorResponse("Only pending cash-ins can be updated", [], 400);
        }

        // --- Validate duplicate order_ids for newly added orders only ---
        $addedOrders     = $data['added_orders'] ?? [];
        $removedOrderIds = $data['removed_order_ids'] ?? [];

        if (!empty($addedOrders)) {
            $addedOrderIds = collect($addedOrders)->pluck('order_id');

            $duplicates = CashIn::where('id', '!=', $id)
                ->where(function ($query) use ($addedOrderIds) {
                    foreach ($addedOrderIds as $orderId) {
                        $query->orWhereJsonContains('orders', ['order_id' => $orderId]);
                    }
                })
                ->pluck('orders')
                ->flatten(1)
                ->pluck('order_id')
                ->intersect($addedOrderIds)
                ->values();

            if ($duplicates->isNotEmpty()) {
                return errorResponse(
                    'Orders ' . $duplicates->implode(', ') . ' are already linked to another cash-in',
                    ['duplicate_order_ids' => $duplicates],
                    422
                );
            }
        }

        return DB::transaction(function () use ($cashIn, $data, $addedOrders, $removedOrderIds) {
            $existingOrders = collect($cashIn->orders ?? []);

            // Remove unselected orders
            $updatedOrders = $existingOrders->filter(
                fn($order) => !in_array($order['order_id'], $removedOrderIds)
            );

            // Append newly added orders
            foreach ($addedOrders as $order) {
                $updatedOrders->push($order);
            }

            $updatedOrders = $updatedOrders->values();

            $cashIn->update([
                'cash_in_amount' => $data['cash_in_amount'],
                'denominations'  => $data['denominations'],
                'vault_id'       => $data['vault_id'],
                'orders'         => $updatedOrders,
            ]);

            return successResponse("Successfully updated cash-in", $cashIn->fresh(), 200);
        });
    }


    public function deleteCashIn($id)
    {
        $cashIn = $this->find($id);

        if (!$cashIn) {
            return errorResponse("Cash-in not found", [], 404);
        }

        if ($cashIn->verifier_status !== 'pending' || $cashIn->approver_status !== 'pending') {
            return errorResponse("Only pending cash-ins can be deleted", [], 400);
        }


        $cashIn->delete();

        return successResponse("Cash-in deleted successfully", [], 200);
    }

    // public function getVerifierAllPendingCashInsBySt atus()
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
    public function getCashInsByVaultId($vaultId)
    {
        $cashIns = $this->cashInRepo->getCashInsByVaultId($vaultId);

        return successResponse("Successfully fetched cash-ins", $cashIns, 200);
    }

    public function approved($cashInId)
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

        info("requiredApprover");
        info($requiredApprover);

        if (!$requiredApprover) {
            return response()->json(['error' => 'You are not assigned as a approver for this CashIn'], 403);
        }

        if ($requiredApprover->approved) {
            return response()->json(['error' => 'You have already approvered this CashIn'], 400);
        }


        // Check if ALL required verifiers have verified
        $totalRequired = $cashIn->requiredApprovers()->count();
        $totalApproved = $cashIn->requiredApprovers()->where('approved', true)->count();


        // Mark as verified in required table
        $requiredApprover->update([
            'approved' => true,
            'approved_at' => now(),
        ]);

        if ($totalApproved === $totalRequired) {

            $result = handleHttpRequest('POST', env('QBITS_SERVICE_BASE_URL') . '/deposit-orders', [
                'token' => env('QBITS_SERVICE_TOKEN'),
            ], [$cashIn]);

            info("result");
            info($result);


            if ($result['success'] === true) {

                $cashIn->approver_status = 'approved';
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

                    $bag->save();
                }

                return successResponse('Cash-in approved successfully', [
                    'cash_in' => $cashIn->fresh(),
                    'bag' => $bag,
                ], 200);
            }

            return errorResponse($result['message'], [], 400);
        }

        return response()->json([
            'message' => '  Cash-in approval recorded',
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
            'verifier_status' => $cashIn->verifier_status,
            'status' => $cashIn->status,
        ]);
    }
}
