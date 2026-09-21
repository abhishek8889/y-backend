<?php

namespace App\Mail;

use App\Models\OrganiserRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganiserRegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganiserRegistration $registration,
        public string $otp,
        public int $otpExpiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.otp_mail_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.organiser-registration-otp',
            with: [
                'otpExpiresInMinutes' => $this->otpExpiresInMinutes,
            ],
        );
    }
}
