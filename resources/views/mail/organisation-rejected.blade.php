<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.organisation_rejected_mail_subject') }}</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            background-color: #f2f2f2;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #000000;
        }

        .card {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
        }

        .heading {
            margin: 0 0 12px;
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .copy {
            margin: 0 0 12px;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
            color: #222222;
        }

        .reason-box {
            margin: 20px 0;
            padding: 16px;
            background: #f7f7f7;
            border: 1px solid #e5e5e5;
            text-align: left;
        }

        .reason-label {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 600;
            color: #444444;
            text-transform: uppercase;
        }

        .reason-text {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #222222;
        }

        .muted {
            margin: 16px 0 0;
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="card">
        <div style="padding: 28px 32px 24px; text-align: center;">
            <img
                src="{{ $message->embed(public_path('assets/yourlist-logo.png')) }}"
                alt="yourlist"
                width="140"
                height="41"
                style="display: block; margin: 0 auto; max-width: 140px; height: auto; border: 0;"
            >
        </div>
        <hr style="border: 0; border-top: 1px solid #d8d8d8; margin: 0 32px;">
        <div style="padding: 36px 40px 32px;">
            <h1 class="heading">{{ __('messages.organisation_rejected_mail_heading') }}</h1>
            <p class="copy">
                {{ __('messages.organisation_rejected_mail_intro', [
                    'name' => $owner->first_name,
                    'organisation' => $organisation->name,
                ]) }}
            </p>
            <div class="reason-box">
                <p class="reason-label">{{ __('messages.organisation_rejected_mail_reason_label') }}</p>
                <p class="reason-text">{{ $reason ?: '—' }}</p>
            </div>
            <p class="muted">{{ __('messages.organisation_rejected_mail_footer') }}</p>
        </div>
    </div>
</body>
</html>
