<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}
    public function register(Request $request)
    {
        return $this->authService->create($request);
    }

    public function login(Request $request)
    {
        return $this->authService->login($request);
    }

    public function logout()
    {
        return $this->authService->logout();
    }

    public function refresh()
    {
        return $this->authService->refresh();
    }
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|different:currentPassword',
        ]);


        if ($validator->fails()) {

            return errorResponse("Validation error", $validator->errors(), 422);
        }

        $user = $this->authService->changePassword($request);
        return successResponse([], "Password changed successfully", 200);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $otpRecord = Otp::verifyOtp(auth()->id(), $request->code);

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $otpRecord->update(['used' => true]);

        $user = auth()->user();
        $user->update([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Email verified successfully']);
    }
}
