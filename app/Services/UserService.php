<?php

namespace App\Services;

use App\Mail\InactiveUserEmailVerification;
use App\Models\Otp;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}
    public function findById($id)
    {
        return $this->userRepository->findById($id);
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

    public function resetPassword($userId)
    {
        $user = $this->findById($userId);

        // Generate token — store in password_reset_tokens table
        $token = \Illuminate\Support\Str::random(64);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => hash('sha256', $token),
                'created_at' => now()
            ]
        );

        // Send email
        \Mail::to($user->email)->send(new \App\Mail\PasswordResetMail($user, $token));

        return response()->json(['message' => 'Password reset email sent']);
    }

    public function confirmResetPassword($request)
    {

        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Direct token comparison — no hashing
        if (!$record || $record->token !== $request->token) {
            return response()->json(['message' => 'Invalid token'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json(['message' => 'Token expired'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Direct bcrypt — no Hash facade (JWT compatible)
        $user->password = bcrypt($request->password);
        $user->save();

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully']);
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

        $data = [
            'module_name' => 'email_verification',
            "recipients" => [
                [
                    'email' => $user->email,
                ]
            ],
            'dynamic_data' => [
                'email' => $user->email,
                'otp' => $otp,
            ],

        ];


        handleHttpNewRequest('POST', env('NAAS_SERVICE_BASE_URL') . '/notification/send', [], $data);

        // Mail::to($user->email)->send(new InactiveUserEmailVerification($user));
    }
}
