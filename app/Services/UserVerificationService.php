<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Log;
use Pippa\NotificationSdkLaravel\DTOs\Recipient;
use Pippa\NotificationSdkLaravel\DTOs\TemplateMessage;
use Pippa\NotificationSdkLaravel\Facades\NotificationService;
use Pippa\NotificationSdkLaravel\Requests\SendMessageRequest;

class UserVerificationService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function sendOtpToPhone($request)
    {

        $user = auth()->user();

        $phoneExists = $this->userService->checkPhoneNumberExistence($request->phone, $user->id);

        if ($phoneExists) {
            return errorResponse("This phone number is already associated with another account.", [], 409);
        }

        if ($user->phone === $request->phone && $user->phone_verified_at) {
            return errorResponse("This phone number is already verified.", [], 409);
        }

        Otp::where('user_id', $user->id)
            ->where('purpose', 'phone_verification')
            ->whereNull('expires_at')
            ->delete();

        $otp = (string) rand(100000, 999999);

        $hashedOtp = bcrypt($otp);

        Otp::create([
            'user_id'    => $user->id,
            'otp'        => $hashedOtp,
            'purpose'    => 'phone_verification',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send OTP via notification service
        $result = NotificationService::send(
            new SendMessageRequest([
                'message' => new TemplateMessage([
                    'to' => [
                        Recipient::phone($request->phone),
                    ],
                    'template' => 'vault_email_verify',
                    'data'     => ['otp' => $otp],
                ]),
            ])
        );

        if (!$result->success) {
            Log::error('Phone OTP send failed', [
                'user_id' => $user->id,
                'phone'   => $request->phone,
                'result'  => $result,
            ]);

            return errorResponse("Failed to send OTP. Please try again later.", [], 500);
        }

        return successResponse("OTP sent successfully.", [], 200);
    }
}
