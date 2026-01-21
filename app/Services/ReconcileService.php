<?php

namespace App\Services;

use App\Repositories\ReconcileRepository;
use App\Repositories\ReconcileRequiredRepository;
use Illuminate\Support\Facades\DB;

class ReconcileService
{

    public function __construct(protected ReconcileRepository $reconcileRepository, protected UserService $userService, protected ReconcileRequiredRepository $reconcileRequired) {}

    public function index($request)
    {
        return $this->reconcileRepository->index($request);
    }

    public function create($data)
    {
        $data['started_by'] = auth()->user()->id;
        $data["reconcile_tran_id"] = $this->generateReconcileId();

        return DB::transaction(function () use ($data) {

            $reconcile = $this->reconcileRepository->createReconcile($data);

            // Get users with 'reconcile.verify' permission
            $verifiers = $this->userService->getAllUsersPermissionByName('reconciliation.verify');

            // Get users with 'reconcile.approve' permission
            $approvers = $this->userService->getAllUsersPermissionByName('reconciliation.approve');

            // Optional: Exclude super-admin or admin (adjust role/spatie check as per your system)
            $verifiers = $verifiers->reject(function ($user) {
                return $user->hasRole(['Super Admin', 'Admin']); // Spatie example

            });

            $approvers = $approvers->reject(function ($user) {
                return $user->hasRole(['Super Admin', 'Admin']);
            });

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
            // Get cash-ins pending verification by this user
            $pendingVerification = $this->reconcileRepository->getPendingForVerifier($user->id);

            // Get cash-ins pending approval (already verified)
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
            $reconcile->load(['requiredVerifiers.user', 'vault', 'bags']),
            200
        );
    }
}
