<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\LogService;
use App\Services\UserService;
use App\Services\UserVerificationService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected UserService $userService,
        protected UserVerificationService $userVerificationService,
        protected LogService $logService
    ) {}
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

        $this->authService->changePassword($request->all());
        return successResponse([], "Password changed successfully", 200);
    }
    public function superAdminChangeUserPassword(Request $request, $id)
    {
        $superadmin = auth()->user()->hasRole('super-admin');

        if (!$superadmin) {
            return errorResponse("You are not authorized to perform this action", [], 403);
        }

        $validator = Validator::make($request->all(), [
            'newPassword' => 'required|string|min:6',
        ]);


        if ($validator->fails()) {
            return errorResponse("Validation error", $validator->errors(), 422);
        }

        $this->authService->superAdminChangeUserPassword($request->all(), $id);
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
            // 'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Email verified successfully']);
    }

    public function verifyPhoneOtp(Request $request)
    {
        $request->validate([
            'otp'   => ['required', 'digits:6'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ]);

        $user = auth()->user();

        // Idempotency guard — already verified with same phone
        if ($user->phone_verified_at) {

            return errorResponse("Phone is already verified.", [], 409);
        }

        // Brute-force guard — max 5 failed attempts per 10 minutes
        $throttleKey = 'otp_verify:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return errorResponse("Too many attempts. Try again in {$seconds} seconds.", [], 429);
        }

        // Fetch latest valid OTP record for this user + purpose
        $otpRecord = Otp::where('user_id', $user->id)
            ->where('purpose', 'phone_verification')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        // FIX: Use password_verify() to compare against bcrypt hash
        if (!$otpRecord || !password_verify($request->otp, $otpRecord->otp)) {
            RateLimiter::hit($throttleKey, 600); // count failed attempt

            return errorResponse("Invalid or expired OTP.", [], 422);
        }

        // OTP matched — clear brute-force counter
        RateLimiter::clear($throttleKey);

        // Mark OTP as consumed
        $otpRecord->update([
            'used'     => true,
            // 'verified_at' => now(),
        ]);

        // Clean up any other stale OTPs for same purpose
        Otp::where('user_id', $user->id)
            ->where('purpose', 'phone_verification')
            ->where('id', '!=', $otpRecord->id)
            // ->whereNull('used_at')
            ->delete();

        // Persist phone + mark verified
        $user->update([
            'phone'             => $request->phone,
            'phone_verified_at' => now(),
            'verified'          => true, // boolean, NOT string 'true'
        ]);

        $data = [
            'phone'             => $user->fresh()->phone,
            'phone_verified_at' => $user->fresh()->phone_verified_at,
        ];

        $this->logService->activityLog("phone_verification", "auth", "User #{$user->name} Phone verified successfully.");

        return successResponse("Phone verified successfully.", $data, 200);
    }

    // public function sendOtpToPhone(Request $request)
    // {
    //     $request->validate([
    //         'phone' => 'nullable',
    //     ]);

    //     if ($request->phone) {
    //         $user = auth()->user();
    //         // $user->update([
    //         //     'phone' => $request->phone,
    //         // ]);


    //         $otp = rand(100000, 999999); // 6 digit OTP

    //         Otp::create([
    //             'user_id'    => $user->id,
    //             'otp'        => $otp,
    //             'purpose'    => 'phone_verification',
    //             'expires_at' => now()->addMinutes(5),
    //         ]);


    //         $data = [
    //             'module_name' => 'email_verification',
    //             "recipients" => [
    //                 [
    //                     'phone' => $request->phone,
    //                 ]
    //             ],
    //             'dynamic_data' => [
    //                 'otp' => $otp,
    //             ],

    //         ];


    //         handleHttpNewRequest('POST', env('NAAS_SERVICE_BASE_URL') . '/notification/send', [], $data);
    //     }

    //     return response()->json(['message' => 'Phone OTP sent successfully']);
    // }

    public function sendOtpToPhone(Request $request)
    {

        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/',],
        ]);

        return $this->userVerificationService->sendOtpToPhone($request);





        // Invalidate any existing unused OTPs for this user/purpose
        // Otp::where('user_id', $user->id)
        //     ->where('purpose', 'phone_verification')
        //     ->whereNull('expires_at')
        //     ->delete();

        // $otp       = (string) rand(100000, 999999);
        // $hashedOtp = bcrypt($otp); // Never store plain OTP

        // Otp::create([
        //     'user_id'    => $user->id,
        //     'otp'        => $hashedOtp,
        //     'purpose'    => 'phone_verification',
        //     'expires_at' => now()->addMinutes(5),
        // ]);

        // $result = NotificationService::send(
        //     new SendMessageRequest([
        //         'message' => new TemplateMessage([
        //             'to' => [

        //                 Recipient::phone($request->phone),
        //                 // Recipient::userId('user_123'),
        //             ],
        //             'template' => 'vault_email_verify',
        //             'data'     => ['otp' => $otp],
        //         ]),
        //     ])
        // );

        // info("NAAS RESPONSE", ['response' => $result]);

        // if (!$result->success) {
        //     \Log::error('Phone OTP send failed', [
        //         'user_id' => $user->id,
        //         'phone'   => $request->phone,
        //         'result'  => $result,
        //     ]);

        //     return response()->json([
        //         'message' => 'Failed to send OTP. Please try again.',
        //         'error'   => $result->message ?? 'Notification service error',
        //     ], 502);
        // }

        // return response()->json([
        //     'message' => 'OTP sent successfully to your phone.',
        // ], 200);
    }

    public function userVerifcation(Request $request)
    {
        $this->userService->userVerifcation($request->all(), auth()->user()->id);
    }
}
