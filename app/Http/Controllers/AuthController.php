<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected UserService $userService
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
            // 'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Email verified successfully']);
    }

    // public function verifyPhoneOtp(Request $request)
    // {
    //     $request->validate([
    //         'otp'   => ['required', 'digits:6'],
    //         'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
    //     ]);

    //     $user = auth()->user();

    //     // Idempotency guard — already verified, no need to proceed
    //     if ($user->phone_verified_at && $user->phone === $request->phone) {
    //         return response()->json([
    //             'message' => 'Phone is already verified.',
    //         ], 200);
    //     }

    //     // Brute-force guard — max 5 failed attempts per 10 minutes
    //     $throttleKey = 'otp_verify:' . $user->id;

    //     if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
    //         $seconds = RateLimiter::availableIn($throttleKey);

    //         return response()->json([
    //             'message' => "Too many attempts. Please try again in {$seconds} seconds.",
    //         ], 429);
    //     }

    //     // Fetch latest unused, unexpired OTP for this user + purpose
    //     $otpRecord = Otp::where('user_id', $user->id)
    //         ->where('purpose', 'phone_verification')
    //         // ->whereNull('verified_at')
    //         // ->whereNull('used_at')
    //         ->where('expires_at', '>', now())
    //         ->latest()
    //         ->first();

    //     // Validate existence and hash match
    //     if (!$otpRecord || !password_verify($request->otp, $otpRecord->otp)) {
    //         RateLimiter::hit($throttleKey, 600); // count failed attempt (10 min window)

    //         return response()->json([
    //             'message' => 'Invalid or expired OTP.',
    //         ], 422);
    //     }

    //     // OTP is valid — clear rate limiter
    //     RateLimiter::clear($throttleKey);

    //     // Mark OTP as consumed (both fields for clarity)
    //     $otpRecord->update([
    //         'used'     => true,
    //         // 'verified_at' => now(),
    //     ]);

    //     // Invalidate all other pending OTPs for same purpose (clean slate)
    //     Otp::where('user_id', $user->id)
    //         ->where('purpose', 'phone_verification')
    //         ->where('id', '!=', $otpRecord->id)
    //         // ->whereNull('used_at')
    //         ->delete();

    //     // Save phone + mark as verified
    //     $user->update([
    //         'phone'             => $request->phone,
    //         'phone_verified_at' => now(),
    //         'verified'          => true,
    //     ]);

    //     return response()->json([
    //         'message' => 'Phone verified successfully.',
    //         'data'    => [
    //             'phone'             => $user->phone,
    //             'phone_verified_at' => $user->phone_verified_at,
    //         ],
    //     ], 200);
    // }

    public function verifyPhoneOtp(Request $request)
    {
        $request->validate([
            'otp'   => ['required', 'digits:6'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ]);

        $user = auth()->user();

        // Idempotency guard — already verified with same phone
        if ($user->phone_verified_at) {
            return response()->json([
                'message' => 'Phone is already verified.',
            ], 200);
        }

        // Brute-force guard — max 5 failed attempts per 10 minutes
        $throttleKey = 'otp_verify:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        // Fetch latest valid OTP record for this user + purpose
        $otpRecord = Otp::where('user_id', $user->id)
            ->where('purpose', 'phone_verification')
            // ->whereNull('verified_at')
            // ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        // FIX: Use password_verify() to compare against bcrypt hash
        if (!$otpRecord || !password_verify($request->otp, $otpRecord->otp)) {
            RateLimiter::hit($throttleKey, 600); // count failed attempt

            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 422);
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

        return response()->json([
            'message' => 'Phone verified successfully.',
            'data'    => [
                'phone'             => $user->fresh()->phone,
                'phone_verified_at' => $user->fresh()->phone_verified_at,
            ],
        ], 200);
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

        $user = auth()->user();

        // Check if phone already exists on another account
        $phoneExists = User::where('phone', $request->phone)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($phoneExists) {
            return response()->json([
                'message' => 'This phone number is already associated with another account.',
            ], 409); // 409 Conflict
        }

        if ($user->phone === $request->phone && $user->phone_verified_at) {
            return response()->json([
                'message' => 'This phone number is already verified on your account.',
            ], 409);
        }

        // Invalidate any existing unused OTPs for this user/purpose
        Otp::where('user_id', $user->id)
            ->where('purpose', 'phone_verification')
            ->whereNull('expires_at')
            ->delete();

        $otp       = (string) rand(100000, 999999);
        $hashedOtp = bcrypt($otp); // Never store plain OTP

        Otp::create([
            'user_id'    => $user->id,
            'otp'        => $hashedOtp,
            'purpose'    => 'phone_verification',
            'expires_at' => now()->addMinutes(5),
        ]);

        $payload = [
            'module_name' => 'email_verification',
            'recipients'  => [
                ['phone' => $request->phone],
            ],
            'dynamic_data' => [
                'otp' => $otp, // Send plain OTP in notification only
            ],
        ];

        $result = handleHttpNewRequest(
            'POST',
            env('NAAS_SERVICE_BASE_URL') . '/notification/send',
            [],
            $payload
        );

        if (!$result['success']) {
            \Log::error('Phone OTP send failed', [
                'user_id' => $user->id,
                'phone'   => $request->phone,
                'result'  => $result,
            ]);

            return response()->json([
                'message' => 'Failed to send OTP. Please try again.',
                'error'   => $result['error'] ?? 'Notification service error',
            ], 502);
        }

        return response()->json([
            'message' => 'OTP sent successfully to your phone.',
        ], 200);
    }

    public function userVerifcation(Request $request)
    {
        $this->userService->userVerifcation($request->all(), auth()->user()->id);
    }
}
