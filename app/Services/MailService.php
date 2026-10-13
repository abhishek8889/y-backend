<?php

namespace App\Services;

use App\Enum\MailSenderEnum;
use App\Mail\AppMail;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MailService extends Service
{
    /**
     * Queue an email through the shared app mailer (async via AppMail ShouldQueue).
     *
     * Call sites keep the same shape used across Auth and other services:
     * $this->mail->send(to:, subject:, view:, data:, sentBy:).
     *
     * @param  string|list<string>  $to
     * @param  array<string, mixed>  $data
     * @param  list<array{path: string, name?: string, mime?: string}>  $attachments
     */
    public function send(
        string|array $to,
        string $subject,
        string $view,
        array $data,
        MailSenderEnum $sentBy,
        array $attachments = [],
    ): void {
        try {
            Mail::to($to)->send(new AppMail($subject, $view, $data, $sentBy, $attachments));
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_BAD_GATEWAY,
                __('auth.mail_failed'),
                $exception->getMessage(),
            );
        }
    }
}
