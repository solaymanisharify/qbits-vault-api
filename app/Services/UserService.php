<?php

namespace App\Services;

use App\Repositories\CashInRequiredRepository;
use App\Repositories\CashOutRequiredRepository;
use App\Repositories\CustodianRepository;
use App\Repositories\ReconcileRequiredRepository;
use App\Repositories\UserRepository;
use App\Repositories\VaultAssignRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Pippa\NotificationSdkLaravel\DTOs\Recipient;
use Pippa\NotificationSdkLaravel\DTOs\TemplateMessage;
use Pippa\NotificationSdkLaravel\Facades\NotificationService;
use Pippa\NotificationSdkLaravel\Requests\SendMessageRequest;
use Spatie\Permission\Models\Role;


class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected LogService $logService,
        protected OtpService $otpService,
        protected VaultAssignRepository $vaultAssignRepository,
        protected CashInRequiredRepository $cashInRequiredRepository,
        protected CashOutRequiredRepository $cashOutRequiredRepository,
        protected CustodianRepository $custodianRepository,
        protected ReconcileRequiredRepository $reconcileRequiredRepository
    ) {}
    public function findById($id)
    {
        return $this->userRepository->findById($id);
    }
    public function findByEmail($email)
    {
        return $this->userRepository->findByEmail($email);
    }
    public function index($request = null)
    {
        $users = $this->userRepository->index($request);

        return successResponse("Successfully fetch all users", $users, 200);
    }
    public function show($id)
    {
        $users = $this->userRepository->show($id);

        return successResponse("Successfully fetch all users", $users, 200);
    }

    public function getAllUsersPermissionByName($name)
    {
        return $this->userRepository->getAllUsersPermissionByName($name);
    }

    public function createUser($request)
    {
        return $this->userRepository->createUser($request);
    }

    public function update($request, $id)
    {
        return $this->userRepository->update($request, $id);
    }
    public function userVerifcation($request, $id)
    {
        return $this->userRepository->userVerifcation($request, $id);
    }

    public function toggleUserStatus($userId)
    {
        $user = $this->findById($userId);
        $user->status = $user->status === 'inactive' ? 'active' : 'inactive';
        $user->save();

        $this->logService->activityLog(
            'updated',
            'user',
            "User {$user->name} ({$user->email}) status changed to {$user->status}",
            [
                $user->toArray(),
                [
                    'role_name' => $user->name,
                    'role_id' => $user->id,

                ]
            ]
        );

        return successResponse("User status toggled successfully", $user, 200);
    }

    public function archiveUser($userId)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return errorResponse("User not found", [], 404);
        }

        $existPendingTasks = $this->checkArchiveEligibility($userId);

        $responseData = json_decode($existPendingTasks->getContent(), true);

        $canArchive = $responseData['data']['can_archive'] ?? false;

        if (!$canArchive) {
            return errorResponse(
                "User has pending tasks and cannot be archived.",
                [],
                422
            );
        }

        $user->status = 'archived';
        $user->save();

        $this->logService->activityLog(
            'updated',
            'user',
            "User {$user->name} ({$user->email}) archived",
            [
                $user->toArray(),
                [
                    'role_name' => $user->name,
                    'role_id' => $user->id,

                ]
            ]
        );

        return successResponse("User archived successfully", $user, 200);
    }


    public function forgetPassword($request)
    {

        $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = $request->email;

        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            return errorResponse("The email domain does not exist or cannot receive mail.", [], 422);
        }

        $user = $this->findByEmail($email);

        if (!$user) {
            return errorResponse("Email not matched", [], 404);
        }

        if ($user->status !== 'active') {
            return errorResponse("Your account is inactive. Please contact support.", [], 403);
        }

        // Generate token — store in password_reset_tokens table
        $token = \Illuminate\Support\Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => hash('sha256', $token),
                'created_at' => now()
            ]
        );

        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        $response = NotificationService::send(
            new SendMessageRequest([
                'message' => new TemplateMessage([
                    'to' => [
                        Recipient::email($user->email),
                    ],
                    'template' => 'vault_forget_password',
                    'data'     => [
                        'name' => $user->name,
                        'resetUrl' => $resetUrl,
                    ],
                ]),
            ])
        );

        return successResponse("Password reset email sent successfully", [], 200);
    }

    public function confirmResetPassword($request)
    {

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || $record->token !== hash('sha256', $request['token'])) {
            return errorResponse("Invalid token", [], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return errorResponse("Token has expired", [], 422);
        }

        $user = $this->findByEmail($request->email);

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return successResponse("Password reset successfully", [], 200);
    }

    public function notVerifiedUserEmailVerification($user)
    {

        $this->otpService->deleteUnusedOtpByUserId($user->id, 'email_verification');

        $otp = rand(100000, 999999); // 6 digit OTP

        $this->otpService->create($user->id, $otp, 'email_verification');

        $response = NotificationService::send(
            new SendMessageRequest([
                'message' => new TemplateMessage([
                    'to' => [
                        Recipient::email($user->email),
                        // Recipient::phone('+8801700000000'),
                        // Recipient::userId('user_123'),
                    ],
                    'template' => 'vault_email_verify',
                    'data'     => ['otp' => $otp],
                ]),
            ])
        );
    }


    public function getVaultCustodians($vaultId)
    {
        $custodianRoleId = Role::where('name', 'custodian')->value('id');

        if (!$custodianRoleId) {
            return errorResponse(['message' => 'Custodian role not found'], [], 404);
        }


        $custodians = $this->vaultAssignRepository->getAssignVaultByVaultIdAndRoleId($vaultId, $custodianRoleId);

        return response()->json($custodians->pluck('user'));
    }

    public function checkPhoneNumberExistence($phone, $userId)
    {
        return $this->userRepository->checkUserPhoneNumberExistenceByUserId($phone, $userId);
    }

    // private function getTrackingMatrices()
    // {
    //     return [
    //         ['table' => 'cash_in_required_approvers',  'column' => 'approved', 'label' => 'Cash In Approval Queue'],
    //         ['table' => 'cash_in_required_verifiers',  'column' => 'verified', 'label' => 'Cash In Verification Queue'],
    //         ['table' => 'cashout_required_approvers',  'column' => 'approved', 'label' => 'Cash Out Approval Queue'],
    //         ['table' => 'cashout_required_verifiers',  'column' => 'verified', 'label' => 'Cash Out Verification Queue'],
    //         ['table' => 'reconcile_required_approvers', 'column' => 'approved', 'label' => 'Reconciliation Approval Queue'],
    //         ['table' => 'reconcile_required_verifiers', 'column' => 'verified', 'label' => 'Reconciliation Verification Queue'],
    //     ];
    // }

    public function checkArchiveEligibility($userId)
    {
        $pendingTasks = [];
        // 1. Check Pending Cash In Verifications
        // =========================================================================
        $pendingInVerifications = $this->cashInRequiredRepository->getPendingVerifierByUserId($userId);

        foreach ($pendingInVerifications as $pivot) {
            $pendingTasks[] = [
                'id'         => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                'cash_in_id' => $pivot->cash_in_id,
                'vault_name' => $pivot->cashIn?->vault?->name ?? 'N/A',
                'type'       => 'Cash In Verification Required',
                'table'      => 'cash_in_required_verifiers'
            ];
        }
        // 2. Check Pending Cash In Approvals
        // =========================================================================
        $pendingInApprovals = $this->cashInRequiredRepository->getPendingApproveByUserId($userId);

        foreach ($pendingInApprovals as $pivot) {
            $pendingTasks[] = [
                'id'         => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                'cash_in_id' => $pivot->cash_in_id,
                'vault_name' => $pivot->cashIn?->vault?->name ?? 'N/A',
                'type'       => 'Cash In Approval Required',
                'table'      => 'cash_in_required_approvers'
            ];
        }
        // 3. Check Pending Cash Out Verifications
        // =========================================================================
        $pendingOutVerifications = $this->cashOutRequiredRepository->getPendingVerificationByUserId($userId);

        foreach ($pendingOutVerifications as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                'cash_out_id' => $pivot->cash_out_id,
                'vault_name'  => $pivot->cashOut?->vault?->name ?? 'N/A',
                'type'        => 'Cash Out Verification Required',
                'table'       => 'cashout_required_verifiers'
            ];
        }
        // 4. Check Pending Cash Out Approvals
        // =========================================================================
        $pendingOutApprovals = $this->cashOutRequiredRepository->getPendingApproveByUserId($userId);

        foreach ($pendingOutApprovals as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                'cash_out_id' => $pivot->cash_out_id,
                'vault_name'  => $pivot->cashOut?->vault?->name ?? 'N/A',
                'type'        => 'Cash Out Approval Required',
                'table'       => 'cashout_required_approvers'
            ];
        }

        $pendingReconcileVerifiers = $this->reconcileRequiredRepository->getPendingVerifierByUserId($userId);

        foreach ($pendingReconcileVerifiers as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->reconcile?->reconcile_tran_id,
                'from_date' => $pivot->reconcile?->from_date,
                'vault_name'  => $pivot->reconcile?->vault?->name ?? 'N/A',
                'type'        => 'Reconcile Verification Required',
                'table'       => 'reconcile_required_verifiers'
            ];
        }
        // 4. Check Pending Custodain Approvals
        // =========================================================================

        $pendingCustodianApprovals = $this->custodianRepository->getPendingCustodianApprovalsByUserId($userId);

        foreach ($pendingCustodianApprovals as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->vault?->name,
                'cash_out_id' => $pivot->cash_out_id,
                'vault_name'  => $pivot->vault?->name ?? 'N/A',
                'type'        => 'Custodian Approval Required',
                'table'       => 'custodian_cash_histories'
            ];
        }
        // 5. Fetch Valid Fallback Successors
        // =========================================================================
        $fallbackUsers = $this->userRepository->getAllActiveUsersWithoutSpecificId($userId);

        $data = [
            'can_archive'    => empty($pendingTasks),
            'pending_tasks'  => $pendingTasks,
            'fallback_users' => $fallbackUsers
        ];

        return successResponse("Successfully fetched archive eligibility", $data, 200);
    }

    public function migrateUser($request, $userId)
    {
        $targetUserId = $request['targetUserId'] ?? null;

        if (empty($userId)) {
            return errorResponse("Target successor user ID is required.", null, 422);
        }

        if (empty($targetUserId)) {
            return errorResponse("Target successor user ID is required.", null, 422);
        }

        DB::beginTransaction();

        try {
            // 1. Re-fetch eligibility
            $response = $this->checkArchiveEligibility($userId);

            $responseData = $response->getData(true);
            $pendingTasks = $responseData['data']['pending_tasks'] ?? [];

            // 2. Migrate pending workflow tasks to target user
            foreach ($pendingTasks as $task) {
                $tableName = $task['table'];

                $foreignKeyColumn = isset($task['cash_in_id']) ? 'cash_in_id' : 'cash_out_id';
                $foreignKeyValue  = $task['cash_in_id'] ?? $task['cash_out_id'];

                if ($tableName == 'custodian_cash_histories') {
                    $user_id = 'custodian_id';
                } else {
                    $user_id = 'user_id';
                }

                DB::table($tableName)
                    ->where($user_id, $userId)
                    ->where($foreignKeyColumn, $foreignKeyValue)
                    ->update([
                        $user_id => $targetUserId,
                        'updated_at' => now()
                    ]);
            }

            // 3. Migrate vault assignments from userId → targetUserId
            $sourceVaultAssignments = DB::table('vault_assigns')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->get();

            foreach ($sourceVaultAssignments as $assignment) {
                $existingAssignment = DB::table('vault_assigns')
                    ->where('user_id', $targetUserId)
                    ->where('vault_id', $assignment->vault_id)
                    ->first();

                if (!$existingAssignment) {
                    // Target has no assignment for this vault — just update user_id directly
                    DB::table('vault_assigns')
                        ->where('id', $assignment->id)
                        ->update([
                            'user_id'    => $targetUserId,
                            'status'     => 'active',
                            'updated_at' => now(),
                        ]);
                } else {
                    // Target already has this vault — merge roles, then delete source row
                    $existingRoles = json_decode($existingAssignment->roles, true) ?? [];
                    $sourceRoles   = json_decode($assignment->roles, true) ?? [];
                    $mergedRoles   = array_values(array_unique(array_merge($existingRoles, $sourceRoles)));

                    DB::table('vault_assigns')
                        ->where('id', $existingAssignment->id)
                        ->update([
                            'roles'      => json_encode($mergedRoles),
                            'status'     => 'active',
                            'updated_at' => now(),
                        ]);

                    // Delete the now-redundant source user row
                    DB::table('vault_assigns')
                        ->where('id', $assignment->id)
                        ->delete();
                }
            }

            DB::commit();

            return successResponse("Workflow responsibilities and vault assignments migrated successfully.", null, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("User Workflow Migration Failure: " . $e->getMessage(), [
                'user_id'        => $userId,
                'target_user_id' => $targetUserId,
            ]);

            return errorResponse("Migration failed: " . $e->getMessage(), null, 500);
        }
    }
}
