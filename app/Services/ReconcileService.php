<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ReconcileRepository;
use App\Repositories\ReconcileRequiredRepository;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Symfony\Component\String\s;

class ReconcileService
{

    public function __construct(protected ReconcileRepository $reconcileRepository, protected UserService $userService, protected ReconcileRequiredRepository $reconcileRequired, protected VaultBagService $vaultBagService) {}

    public function index($request)
    {
        return $this->reconcileRepository->index($request);
    }
    public function findById($id)
    {
        return $this->reconcileRepository->findById($id);
    }
    public function getLatestReconcile()
    {
        return $this->reconcileRepository->getLatestReconcile();
    }

    public function create($data)
    {
        $data['started_by'] = auth()->user()->id;
        $data["reconcile_tran_id"] = $this->generateReconcileId();

        return DB::transaction(function () use ($data) {

            $reconcile = $this->reconcileRepository->createReconcile($data);

            // Helper to get effective users for a permission, considering overrides
            $getEffectiveUsers = function ($permissionName) {
                $permission = Permission::findByName($permissionName);
                $permissionId = $permission->id;

                // Get users who have the permission via roles or direct assignment (Spatie)
                $usersWithPermission = User::permission($permissionName)->get();

                // Get user_ids with override granted=false (to remove)
                $overridesFalse = DB::table('user_permission_overrides')
                    ->where('permission_id', $permissionId)
                    ->where('granted', false)
                    ->pluck('user_id');

                // Remove those with granted=false
                $usersWithPermission = $usersWithPermission->whereNotIn('id', $overridesFalse);

                // Get user_ids with override granted=true (to add if not already included)
                $overridesTrue = DB::table('user_permission_overrides')
                    ->where('permission_id', $permissionId)
                    ->where('granted', true)
                    ->pluck('user_id');

                // Get users for granted=true
                $additionalUsers = User::whereIn('id', $overridesTrue)->get();

                // Merge and unique
                $effectiveUsers = $usersWithPermission->concat($additionalUsers)->unique('id');

                // Exclude super-admin or admin
                $effectiveUsers = $effectiveUsers->reject(function ($user) {
                    return $user->hasRole(['Super Admin', 'Admin']);
                });

                return $effectiveUsers;
            };

            // Get effective verifiers
            $verifiers = $getEffectiveUsers('reconciliation.verify');

            // Get effective approvers
            $approvers = $getEffectiveUsers('reconciliation.approve');

            // Create verifier records
            foreach ($verifiers as $verifier) {
                $this->reconcileRequired->createVerifier([
                    'reconcile_id' => $reconcile->id,
                    'user_id'    => $verifier->id,
                ]);
            }

            // Create approver records
            foreach ($approvers as $approver) {
                $this->reconcileRequired->createApprover([
                    'reconcile_id' => $reconcile->id,
                    'user_id'    => $approver->id,
                ]);
                // Or if you have a separate method/table:
                // $this->cashInRequired->createApprover([...]);
            }

            return successResponse("Successfully created cash-in", [], 200);
        });
    }

    private function generateReconcileId()
    {
        $prefix = 'REC-';
        $date = date('Ymd'); // Format: 20260119
        $number = str_pad(rand(1, 99999), 4, '0', STR_PAD_LEFT); // 5-digit random number

        return $prefix . $date . $number;
    }

    public function getVerifierAllPendingReconcilesByStatus()
    {
        $user = auth()->user();

        $canVerify = $user->can('reconciliation.verify');
        $canApprove = $user->can('reconciliation.approve');

        // Check if user has at least one required permission
        if (!$canVerify && !$canApprove) {
            return errorResponse('Unauthorized', 403);
        }

        // If user has BOTH permissions, return combined data
        if ($canVerify && $canApprove) {
            // Get reconcile pending verification by this user
            $pendingVerification = $this->reconcileRepository->getPendingForVerifier($user->id);

            // Get reconcile pending approval (already verified)
            $pendingApproval = $this->reconcileRepository->getPendingForApprover();

            // Merge both collections and remove duplicates by ID
            $reconcile = $pendingVerification->merge($pendingApproval)->unique('id');
        }
        // If user has ONLY verify permission
        elseif ($canVerify) {
            $reconcile = $this->reconcileRepository->getPendingForVerifier($user->id);
        }
        // If user has ONLY approve permission
        else {
            $reconcile = $this->reconcileRepository->getPendingForApprover();
        }

        return successResponse(
            "Successfully fetched pending reconciliations",
            $reconcile->load(['vault.bags', 'requiredVerifiers.user', 'startedBy:id,name', 'completedBy']),
            200
        );
    }

    public function verify($request, $reconcileId)
    {

        $user = auth()->user();
        $reconcile = $this->findById($reconcileId);


        // Must have permission
        if (!$user->can('reconciliation.verify')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // $request->validate([
        //     'action' => 'required|in:verify,approve,reject',
        //     'note' => 'nullable|string|max:500',
        // ]);

        $action = $request["action"];

        // Check if this user is a required verifier for this CashIn
        $requiredVerifier = $reconcile->requiredVerifiers()
            ->where('user_id', $user->id)
            ->first();


        if (!$requiredVerifier) {
            return response()->json(['error' => 'You are not assigned as a verifier for this Reconcile'], 403);
        }

        if ($requiredVerifier->verified) {
            return response()->json(['error' => 'You have already verified this Reconcile'], 400);
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
        $totalRequired = $reconcile->requiredVerifiers()->count();
        $totalVerified = $reconcile->requiredVerifiers()->where('verified', true)->count();

        if ($totalVerified === $totalRequired) {
            $reconcile->verifier_status = 'verified';
            $reconcile->save();
        }

        // Handle approve/reject (only if user has permission)
        if ($action === 'approve' && $user->can('cash-in.approve')) {
            $reconcile->status = 'approved';
            $reconcile->save();
        } elseif ($action === 'reject' && $user->can('cash-in.reject')) {
            $reconcile->status = 'rejected';
            $reconcile->save();
        }

        return response()->json([
            'message' => ucfirst($action) . ' recorded successfully',
            'verifier_status' => $reconcile->verifier_status,
            'status' => $reconcile->status,
        ]);
    }

    public function startReconcile($reconcileId)
    {
        $reconcile = $this->findById($reconcileId);

        if ($reconcile->status !== 'pending' || $reconcile->is_locked) {
            return response()->json(['error' => 'Cannot start reconciliation'], 400);
        }

        $reconcile->status = 'in_progress';
        $reconcile->completed_by = auth()->user()->id;
        $reconcile->is_locked = true;
        $reconcile->locked_until = now()->addHours(24);
        $reconcile->save();

        return response()->json(['success' => true]);
    }

    public function latestReconcile()
    {
        $reconcile = $this->getLatestReconcile();
        return successResponse("Successfully fetched latest reconcile", $reconcile, 200);
    }
    public function checkReconcile()
    {
        $reconciliation = $this->getLatestReconcile();

        if (!$reconciliation) {
            return response()->json(['is_locked' => false, 'status' => 'completed']);
        }

        return response()->json([
            'is_locked' => $reconciliation->is_locked,
            'status' => $reconciliation->status,
        ]);
    }
    public function completeReconcile($data, $id)
    {
        $reconcile = $this->findById($id);

        $reconcile->resolution_reason   = $data['resolution_reason'] ?? null;
        $reconcile->status              = 'completed';
        $reconcile->variance_type       = $data['variance_type'] ?? 'unknown';
        $reconcile->variance            = $data['total_variance'] ?? 0;
        $reconcile->approver_status     = 'approved';
        $reconcile->completed_by        = auth()->user()->id;
        $reconcile->is_locked           = false;
        $reconcile->locked_until        = null;

        $expected_balance = 0;
        $counted_balance  = 0;

        foreach ($data['variances_bags'] ?? [] as $item) {
            $bag = $this->vaultBagService->getBagByBagId($item['bag_id']);

            if (!$bag) {
                continue; // or throw/log error
            }

            $expected_balance += $bag->current_amount ?? 0;
            $counted_balance  += $item['counted_amount'] ?? 0;

            $reconcile->varianceBags()->attach($item['bag_id'], [
                'difference'            => $item['difference'] ?? 0,
                'note'                  => $item['note'] ?? null,
                'counted_amount'        => $item['counted_amount'] ?? 0,
                'counted_denominations' => json_encode($item['counted_denominations'] ?? []),
                'expected_amount'       => $bag->current_amount ?? 0,
            ]);

            if ($item['difference'] < 0) {
                $bag->current_amount -= $item['difference'];
                $bag->save();
            }
            if ($item['difference'] > 0) {
                $bag->current_amount += $item['difference'];
                $bag->save();
            }
        }

        $reconcile->expected_balance = $expected_balance;
        $reconcile->counted_balance  = $counted_balance;
        $reconcile->save();
    }
}
