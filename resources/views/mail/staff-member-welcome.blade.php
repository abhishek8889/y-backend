<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.organisation_staff_welcome_mail_subject', ['organisation' => $organisationName]) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f2f2f2; font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #000000;">
    @php
        $fullName = trim($user->first_name.' '.$user->last_name);
        $rows = [
            [__('messages.organisation_staff_welcome_mail_label_name'), $fullName],
            [__('messages.organisation_staff_welcome_mail_label_email'), $user->email],
            [__('messages.organisation_staff_welcome_mail_label_role'), $roleNames],
            [__('messages.organisation_staff_welcome_mail_label_password'), $password],
        ];
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f2f2f2;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; width: 100%; background-color: #ffffff;">
                    <tr>
                        <td align="center" style="padding: 28px 32px 24px;">
                            <img
                                src="{{ $message->embed(public_path('assets/yourlist-logo.png')) }}"
                                alt="yourlist"
                                width="140"
                                height="41"
                                style="display: block; max-width: 140px; height: auto; border: 0;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px;">
                            <hr style="border: 0; border-top: 1px solid #d8d8d8; margin: 0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 40px 32px;">
                            <h1 style="margin: 0 0 12px; font-size: 24px; font-weight: 700; text-transform: uppercase; text-align: left; color: #000000;">
                                {{ __('messages.organisation_staff_welcome_mail_heading') }}
                            </h1>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.5; text-align: left; color: #222222;">
                                {{ __('messages.organisation_staff_welcome_mail_intro', [
                                    'name' => $user->first_name,
                                    'organisation' => $organisationName,
                                ]) }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
                                @foreach ($rows as [$label, $value])
                                    <tr>
                                        <td width="120" valign="top" style="width: 120px; padding: 8px 16px 8px 0; font-size: 14px; line-height: 1.5; font-weight: 700; color: #000000; text-align: left;">
                                            {{ $label }}
                                        </td>
                                        <td valign="top" style="padding: 8px 0; font-size: 14px; line-height: 1.5; font-weight: 400; color: #222222; text-align: left;">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.5; text-align: left; color: #222222;">
                                {{ __('messages.organisation_staff_welcome_mail_access') }}
                            </p>
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; text-align: left; color: #666666;">
                                {{ __('messages.organisation_staff_welcome_mail_footer') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
