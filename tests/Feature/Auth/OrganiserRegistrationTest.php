<?php

use App\Enum\MailSenderEnum;
use App\Mail\AppMail;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;

test('organiser registration sends an otp email from the platform', function () {
    Mail::fake();
    Str::createRandomStringsUsing(fn (): string => 'abcdef');

    $this->postJson(route('organiser.register'), organiserRegistrationPayload())
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.registered'))
        ->assertJsonPath('data.email', 'ada@example.com');

    $this->assertDatabaseHas('organiser_registrations', [
        'email' => 'ada@example.com',
        'otp' => 'ABCDEF',
        'org_name' => 'Analytical Engines Ltd',
        'org_email' => 'hello@engines.example',
        'org_city' => 'London',
    ]);

    Mail::assertSent(AppMail::class, function (AppMail $mail): bool {
        return $mail->hasTo('ada@example.com')
            && $mail->mailView === 'mail.organiser-registration-otp'
            && $mail->data['otp'] === 'ABCDEF'
            && $mail->data['otpExpiresInMinutes'] === 10
            && $mail->sentBy === MailSenderEnum::PLATFORM;
    });

    Str::createRandomStringsNormally();
});

test('organiser registration returns 502 when the otp email cannot be sent', function () {
    $pending = Mockery::mock(PendingMail::class);
    $pending->expects('send')
        ->andThrow(new TransportException('SMTP authentication failed.'));

    Mail::expects('to')->andReturn($pending);

    $this->postJson(route('organiser.register'), organiserRegistrationPayload())
        ->assertStatus(502)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.mail_failed'))
        ->assertJsonPath('error', 'SMTP authentication failed.');

    $this->assertDatabaseHas('organiser_registrations', [
        'email' => 'ada@example.com',
    ]);
});

/**
 * @return array<string, string>
 */
function organiserRegistrationPayload(): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '5551234567',
        'country_code' => '+1',
        'country' => 'US',
        'password' => 'password',
        'confirm_password' => 'password',
        'org_organiser_name' => 'Ada Lovelace',
        'org_name' => 'Analytical Engines Ltd',
        'org_email' => 'hello@engines.example',
        'org_country_code' => '+1',
        'org_phone' => '5559876543',
        'org_country' => 'US',
        'org_city' => 'London',
        'org_website' => 'https://engines.example',
    ];
}
