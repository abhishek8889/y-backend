<?php

namespace App\Mail;

use App\Enum\MailSenderEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{path: string, name?: string, mime?: string}>  $fileAttachments
     */
    public function __construct(
        public string $mailSubject,
        public string $mailView,
        public array $data,
        public MailSenderEnum $sentBy,
        public array $fileAttachments = [],
    ) {
        $this->afterCommit();
    }

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

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            function (array $attachment): Attachment {
                $mailAttachment = Attachment::fromPath($attachment['path']);

                if (filled($attachment['name'] ?? null)) {
                    $mailAttachment = $mailAttachment->as((string) $attachment['name']);
                }

                if (filled($attachment['mime'] ?? null)) {
                    $mailAttachment = $mailAttachment->withMime((string) $attachment['mime']);
                }

                return $mailAttachment;
            },
            $this->fileAttachments,
        );
    }
}
