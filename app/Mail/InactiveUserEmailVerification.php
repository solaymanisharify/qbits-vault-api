<?php

namespace App\Mail;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InactiveUserEmailVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $otp;

    public function __construct($user)
    {
        $this->user = $user;
        $this->otp = $this->generateOtp($user);
    }

    private function generateOtp(User $user)
    {
        $otp = rand(100000, 999999); // 6 digit OTP

        Otp::create([
            'user_id'    => $user->id,
            'otp'        => $otp,
            'purpose'    => 'email_verification',
            'expires_at' => now()->addMinutes(15),
        ]);

        return $otp;
    }
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email - QBits Vault',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.inactive_user_email_verification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
