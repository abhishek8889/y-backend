<?php

use App\Enum\MailSenderEnum;
use App\Exceptions\ServiceException;
use App\Mail\AppMail;
use App\Services\MailService;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

test('mail service sends through AppMail with the given sender', function () {
    Mail::fake();

    app(MailService::class)->send(
        to: 'ada@example.com',
        subject: 'Verify your account',
        view: 'mail.organiser-registration-otp',
        data: [
            'otp' => 'ABCDEF',
            'otpExpiresInMinutes' => 10,
        ],
        sentBy: MailSenderEnum::PLATFORM,
    );

    Mail::assertSent(AppMail::class, function (AppMail $mail): bool {
        return $mail->hasTo('ada@example.com')
            && $mail->mailSubject === 'Verify your account'
            && $mail->mailView === 'mail.organiser-registration-otp'
            && $mail->data['otp'] === 'ABCDEF'
            && $mail->sentBy === MailSenderEnum::PLATFORM;
    });
});

test('mail service fails with the transport error when send fails', function () {
    Mail::shouldReceive('to')
        ->once()
        ->andReturnSelf();

    Mail::shouldReceive('send')
        ->once()
        ->andThrow(new TransportException('SMTP authentication failed.'));

    expect(fn () => app(MailService::class)->send(
        to: 'ada@example.com',
        subject: 'Verify your account',
        view: 'mail.organiser-registration-otp',
        data: [],
        sentBy: MailSenderEnum::PLATFORM,
    ))->toThrow(
        ServiceException::class,
        __('auth.mail_failed'),
    );
});
