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
     * Send an email through the shared app mailer.
     *
     * @param  string|list<string>  $to
     * @param  array<string, mixed>  $data
     */
    public function send(
        string|array $to,
        string $subject,
        string $view,
        array $data,
        MailSenderEnum $sentBy,
    ): void {
        try {
            Mail::to($to)->send(new AppMail($subject, $view, $data, $sentBy));
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
