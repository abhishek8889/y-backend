<?php

namespace App\Mail;

use App\Enum\MailSenderEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $mailSubject,
        public string $mailView,
        public array $data,
        public MailSenderEnum $sentBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: $this->mailView,
            with: [
                ...$this->data,
                'sentBy' => $this->sentBy,
            ],
        );
    }
}
