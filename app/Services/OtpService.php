<?php

namespace App\Services;

use App\Repositories\OtpRepository;

class OtpService
{

    public function __construct(protected OtpRepository $otpRepository) {}
    public function create($userId, $otp, $purpose)
    {
        $this->otpRepository->create($userId, $otp, $purpose);
    }
    public function deleteUnusedOtpByUserId($userId, $purpose)
    {
        $this->otpRepository->deleteUnusedOtpByUserId($userId, $purpose);
    }
}
