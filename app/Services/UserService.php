<?php

namespace App\Services;

use App\Mail\InactiveUserEmailVerification;
use App\Models\Otp;
use App\Models\User;
use App\Models\VaultAssign;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Pippa\NotificationSdkLaravel\DTOs\Recipient;
use Pippa\NotificationSdkLaravel\DTOs\TemplateMessage;
use Pippa\NotificationSdkLaravel\Facades\NotificationService;
use Pippa\NotificationSdkLaravel\Requests\SendMessageRequest;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\error;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}
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

        $user = User::where('email', $request->email)->firstOrFail();

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return successResponse("Password reset successfully", [], 200);
    }

    public function notVerifiedUserEmailVerification($user)
    {

        // Optional: Delete old unused OTPs
        Otp::where('user_id', $user->id)
            ->where('purpose', 'email_verification')
            ->where('used', false)
            ->delete();

        $otp = rand(100000, 999999); // 6 digit OTP

        Otp::create([
            'user_id'    => $user->id,
            'otp'        => $otp,
            'purpose'    => 'email_verification',
            'expires_at' => now()->addMinutes(15),
        ]);

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


        // handleHttpNewRequest('POST', env('NAAS_SERVICE_BASE_URL') . '/notification/send', [], $data);

        // Mail::to($user->email)->send(new InactiveUserEmailVerification($user));
    }


    public function getVaultCustodians($vaultId)
    {
        $custodianRoleId = Role::where('name', 'custodian')->value('id');

        if (!$custodianRoleId) {
            return errorResponse(['message' => 'Custodian role not found'], [], 404);
        }


        $custodians = VaultAssign::where('vault_id', $vaultId)
            ->where('status', 'active')
            ->whereJsonContains('roles', $custodianRoleId)
            ->with('user:id,name,email,status')
            ->get(['user_id', 'roles']);

        return response()->json($custodians->pluck('user'));
    }

    public function checkPhoneNumberExistence($phone, $userId)
    {
        return User::where('phone', $phone)
            ->where('id', '!=', $userId)
            ->exists();
    }

    private function getTrackingMatrices()
    {
        return [
            ['table' => 'cash_in_required_approvers',  'column' => 'approved', 'label' => 'Cash In Approval Queue'],
            ['table' => 'cash_in_required_verifiers',  'column' => 'verified', 'label' => 'Cash In Verification Queue'],
            ['table' => 'cashout_required_approvers',  'column' => 'approved', 'label' => 'Cash Out Approval Queue'],
            ['table' => 'cashout_required_verifiers',  'column' => 'verified', 'label' => 'Cash Out Verification Queue'],
            ['table' => 'reconcile_required_approvers', 'column' => 'approved', 'label' => 'Reconciliation Approval Queue'],
            ['table' => 'reconcile_required_verifiers', 'column' => 'verified', 'label' => 'Reconciliation Verification Queue'],
        ];
    }

    public function checkArchiveEligibility($userId)
    {
        $pendingTasks = [];

        // =========================================================================
        // 1. Check Pending Cash In Verifications
        // =========================================================================
        $pendingInVerifications = \App\Models\CashInRequiredVerifier::with(['cashIn.vault'])
            ->where('user_id', $userId)
            ->where('verified', false)
            ->get();

        foreach ($pendingInVerifications as $pivot) {
            $pendingTasks[] = [
                'id'         => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                'cash_in_id' => $pivot->cash_in_id,
                'vault_name' => $pivot->cashIn?->vault?->name ?? 'N/A',
                'type'       => 'Cash In Verification Required',
                'table'      => 'cash_in_required_verifiers'
            ];
        }

        // =========================================================================
        // 2. Check Pending Cash In Approvals
        // =========================================================================
        $pendingInApprovals = \App\Models\CashInRequiredApprover::with(['cashIn.vault'])
            ->where('user_id', $userId)
            ->where('approved', false)
            ->get();

        foreach ($pendingInApprovals as $pivot) {
            $pendingTasks[] = [
                'id'         => $pivot->cashIn?->tran_id ?? "TXN-IN-{$pivot->cash_in_id}",
                'cash_in_id' => $pivot->cash_in_id,
                'vault_name' => $pivot->cashIn?->vault?->name ?? 'N/A',
                'type'       => 'Cash In Approval Required',
                'table'      => 'cash_in_required_approvers'
            ];
        }

        // =========================================================================
        // 3. Check Pending Cash Out Verifications
        // =========================================================================
        $pendingOutVerifications = \App\Models\CashoutRequiredVerifier::with(['cashOut.vault'])
            ->where('user_id', $userId)
            ->where('verified', false)
            ->get();

        foreach ($pendingOutVerifications as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                'cash_out_id' => $pivot->cash_out_id,
                'vault_name'  => $pivot->cashOut?->vault?->name ?? 'N/A',
                'type'        => 'Cash Out Verification Required',
                'table'       => 'cashout_required_verifiers'
            ];
        }

        // =========================================================================
        // 4. Check Pending Cash Out Approvals
        // =========================================================================
        $pendingOutApprovals = \App\Models\CashoutRequiredApprover::with(['cashOut.vault'])
            ->where('user_id', $userId)
            ->where('approved', false)
            ->get();

        foreach ($pendingOutApprovals as $pivot) {
            $pendingTasks[] = [
                'id'          => $pivot->cashOut?->tran_id ?? "TXN-OUT-{$pivot->cash_out_id}",
                'cash_out_id' => $pivot->cash_out_id,
                'vault_name'  => $pivot->cashOut?->vault?->name ?? 'N/A',
                'type'        => 'Cash Out Approval Required',
                'table'       => 'cashout_required_approvers'
            ];
        }

        // =========================================================================
        // 5. Fetch Valid Fallback Successors
        // =========================================================================
        $fallbackUsers = \App\Models\User::where('id', '!=', $userId)
            ->where('status', 'active')
            ->select('id', 'name', 'email')
            ->get();

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

        DB::beginTransaction();

        try {
            // 1. Re-fetch eligibility
            $response = $this->checkArchiveEligibility($userId);

            // FIX: Extract raw data from the JsonResponse object safely
            $responseData = $response->getData(true);
            $pendingTasks = $responseData['data']['pending_tasks'] ?? [];

            foreach ($pendingTasks as $task) {
                $tableName = $task['table'];

                // Determine the correct foreign key column context based on the task payload type
                $foreignKeyColumn = isset($task['cash_in_id']) ? 'cash_in_id' : 'cash_out_id';
                $foreignKeyValue  = $task['cash_in_id'] ?? $task['cash_out_id'];

                // Safely reassign the task row to the new target user
                DB::table($tableName)
                    ->where('user_id', $userId)
                    ->where($foreignKeyColumn, $foreignKeyValue)
                    ->update([
                        'user_id'    => $targetUserId,
                        'updated_at' => now()
                    ]);
            }


            DB::commit();

            return successResponse("Workflow responsibilities migrated successfully and profile archived.", null, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("User Workflow Migration Failure: " . $e->getMessage(), [
                'user_id' => $userId,
                'target_user_id' => $userId
            ]);

            return errorResponse("Migration failed: " . $e->getMessage(), null, 500);
        }
    }
}
