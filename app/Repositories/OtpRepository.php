<?php

namespace App\Repositories;

use App\Models\Otp;

class OtpRepository
{

    public function create($userId, $otp, $purpose)
    {
        Otp::create([
            'user_id'    => $userId,
            'otp'        => $otp,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes(15),
        ]);
    }
    public function getLatestOtpByUserId($userId, $purpose)
    {
        return Otp::where('user_id', $userId)
            ->where('purpose', $purpose)
            ->latest()
            ->first();
    }
    public function deleteUnusedOtpByUserId($userId, $purpose)
    {
         Otp::where('user_id', $userId)
            ->where('purpose', $purpose)
            ->where('used', false)
            ->delete();
    }
}
