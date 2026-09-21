<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.new_organiser_mail_subject') }}</title>
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
            margin: 0 0 8px;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
            color: #222222;
        }

        .muted {
            margin: 0;
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
            <h1 class="heading">{{ __('auth.new_organiser_mail_heading') }}</h1>
            <p class="copy">{{ __('auth.new_organiser_mail_intro') }}</p>
            <p class="copy"><strong>{{ $organiser->first_name }} {{ $organiser->last_name }}</strong></p>
            <p class="muted">{{ $organiser->email }}</p>
            <p class="muted">{{ $organisation->name }}</p>
            <p class="muted" style="margin-top: 16px;">{{ __('auth.new_organiser_mail_footer') }}</p>
        </div>
    </div>
</body>
</html>
