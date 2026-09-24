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
            margin: 0 0 24px;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
            color: #222222;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 24px;
        }

        .details th,
        .details td {
            padding: 8px 0;
            font-size: 14px;
            line-height: 1.5;
            vertical-align: top;
            text-align: left;
        }

        .details th {
            width: 40%;
            font-weight: 600;
            color: #444444;
            padding-right: 12px;
        }

        .details td {
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
    @php
        $fullName = trim($organiser->first_name.' '.$organiser->last_name);
        $phone = trim(($organiser->country_code ?? '').' '.($organiser->phone ?? ''));
        $addressParts = array_filter([
            $organisation->address1,
            $organisation->address2,
            $organisation->city,
            $organisation->postal_code,
            $organisation->country,
        ]);
        $address = $addressParts === [] ? '—' : implode(', ', $addressParts);
    @endphp
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

            <table role="presentation" class="details" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_name') }}</th>
                    <td>{{ $fullName !== '' ? $fullName : '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_email') }}</th>
                    <td>{{ $organiser->email ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_phone') }}</th>
                    <td>{{ $phone !== '' ? $phone : '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_address') }}</th>
                    <td>{{ $address }}</td>
                </tr>
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_organisation_name') }}</th>
                    <td>{{ $organisation->name ?: '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('auth.new_organiser_mail_label_organisation_website') }}</th>
                    <td>
                        @if (filled($organisation->website))
                            <a href="{{ $organisation->website }}" style="color: #222222;">{{ $organisation->website }}</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            </table>

            <p class="muted">{{ __('auth.new_organiser_mail_footer') }}</p>
        </div>
    </div>
</body>
</html>
