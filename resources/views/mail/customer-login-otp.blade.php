<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('auth.login_otp_mail_subject') }}</title>
    <style type="text/css">
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f2f2f2;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #000000;
        }

        .wrapper {
            width: 100%;
            background-color: #f2f2f2;
            padding: 40px 16px;
        }

        .card {
            width: 100%;
            max-width: 560px;
            background-color: #ffffff;
            margin: 0 auto;
        }

        .divider {
            border: 0;
            border-top: 1px solid #d8d8d8;
            margin: 0;
        }

        .logo {
            display: block;
            margin: 0 auto;
            max-width: 140px;
            height: auto;
        }

        .heading {
            margin: 0 0 12px;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1.2;
            text-transform: uppercase;
            text-align: center;
            color: #000000;
        }

        .copy {
            margin: 0 0 12px;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 15px;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            color: #222222;
        }

        .otp {
            margin: 28px 0;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 0.28em;
            line-height: 1.2;
            text-align: center;
            text-transform: uppercase;
            color: #000000;
        }

        .muted {
            margin: 0;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            color: #666666;
        }

        .footer {
            margin: 0;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            color: #888888;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" class="card" cellpadding="0" cellspacing="0" border="0" width="560">
                        <tr>
                            <td align="center" style="padding: 28px 32px 24px;">
                                <img
                                    class="logo"
                                    src="{{ $message->embed(public_path('assets/yourlist-logo.png')) }}"
                                    alt="yourlist"
                                    width="140"
                                    height="41"
                                    style="display: block; margin: 0 auto; max-width: 140px; height: auto; border: 0;"
                                >
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 0 32px;">
                                <hr class="divider">
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding: 36px 40px 16px;">
                                <h1 class="heading">{{ __('auth.login_otp_mail_heading') }}</h1>
                                <p class="copy">
                                    {{ __('auth.login_otp_mail_intro', ['name' => $name]) }}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding: 0 40px;">
                                <p class="otp">{{ $otp }}</p>
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding: 0 40px 32px;">
                                <p class="muted">{{ __('auth.otp_mail_expiry', ['minutes' => $otpExpiresInMinutes]) }}</p>
                                <p class="muted" style="margin-top: 8px;">{{ __('auth.otp_mail_footer') }}</p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 0 32px;">
                                <hr class="divider">
                            </td>
                        </tr>

                        <tr>
                            <td align="center" style="padding: 20px 32px 28px;">
                                <p class="footer">yourlist</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
