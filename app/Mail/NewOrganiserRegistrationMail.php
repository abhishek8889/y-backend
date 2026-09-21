<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOrganiserRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $organiser,
        public Organisation $organisation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.new_organiser_mail_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.new-organiser-registration',
        );
    }
}
