<?php

namespace App\Services;

use App\Mail\InactiveUserEmailVerification;
use App\Models\Otp;
use App\Models\User;
use App\Models\VaultAssign;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $user->status = 'archived';
        $user->save();

        return successResponse("User archived successfully", $user, 200);
    }

    public function forgetPassword($request)
    {
        info($request);
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

        info("NAAS RESPONSE", ['response' => $response]);


        // handleHttpNewRequest('POST', env('NAAS_SERVICE_BASE_URL') . '/notification/send', [], $data);

        // Mail::to($user->email)->send(new InactiveUserEmailVerification($user));
    }


    public function getVaultCustodians($vaultId)
    {
        $custodianRoleId = Role::where('name', 'custodian')->value('id');

        if (!$custodianRoleId) {
            return errorResponse(['message' => 'Custodian role not found'], [], 404);
        }

        info($custodianRoleId);


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
}
