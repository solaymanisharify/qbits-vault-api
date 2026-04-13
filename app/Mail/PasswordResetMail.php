<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $token;

    public function __construct($user, $token)
    {
        $this->user  = $user;
        $this->token = $token;
    }

    public function build()
    {
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->user->email);

        return $this->subject('Reset Your Password')
            ->view('emails.password_reset')
            ->with([
                'name'     => $this->user->name,
                'resetUrl' => $resetUrl,
            ]);
    }
}
