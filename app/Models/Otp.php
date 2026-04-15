<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = ['user_id', 'otp', 'purpose', 'expires_at', 'used'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper: Check if OTP is valid
    public static function verifyOtp($userId, $otp)
    {
        return self::where('user_id', $userId)
            ->where('otp', $otp)
            ->where('purpose', 'email_verification')
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();
    }
}
