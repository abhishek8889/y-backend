<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('messages.order_confirmation_mail_subject', ['event' => $primary_event_name]) }}</title>
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
            max-width: 600px;
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
            font-size: 15px;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            color: #222222;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #000000;
        }

        .detail {
            margin: 0 0 8px;
            font-size: 14px;
            line-height: 1.5;
            color: #222222;
        }

        .muted {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: #666666;
        }

        .footer {
            margin: 0;
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
            color: #888888;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            padding: 10px 8px;
            border-bottom: 1px solid #d8d8d8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #666666;
            text-align: left;
        }

        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #eeeeee;
            font-size: 14px;
            color: #222222;
            vertical-align: top;
        }

        .items-table .num {
            text-align: right;
            white-space: nowrap;
        }

        .total-row {
            margin: 16px 0 0;
            font-size: 16px;
            font-weight: 700;
            text-align: right;
            color: #000000;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" class="card" cellpadding="0" cellspacing="0" border="0" width="600">
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
                                <h1 class="heading">{{ __('messages.order_confirmation_mail_heading') }}</h1>
                                <p class="copy">{{ __('messages.order_confirmation_mail_intro') }}</p>
                                <p class="muted" style="text-align: center;">
                                    {{ __('messages.order_confirmation_mail_order_ref', ['order' => $order_unique_id]) }}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 8px 40px 24px;">
                                <h2 class="section-title">{{ __('messages.order_confirmation_mail_order_details') }}</h2>
                                <table role="presentation" class="items-table" cellpadding="0" cellspacing="0" border="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th align="left">{{ __('messages.order_confirmation_mail_col_offer') }}</th>
                                            <th align="right" class="num">{{ __('messages.order_confirmation_mail_col_qty') }}</th>
                                            <th align="right" class="num">{{ __('messages.order_confirmation_mail_col_price') }}</th>
                                            <th align="right" class="num">{{ __('messages.order_confirmation_mail_col_total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($line_items as $item)
                                            <tr>
                                                <td>{{ $item['offer_name'] }}</td>
                                                <td class="num">{{ $item['quantity'] }}</td>
                                                <td class="num">{{ $item['formatted_unit_price'] }}</td>
                                                <td class="num">{{ $item['formatted_total_price'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <p class="total-row">
                                    {{ __('messages.order_confirmation_mail_total', ['total' => $formatted_total]) }}
                                </p>
                            </td>
                        </tr>

                        @foreach ($events as $event)
                            <tr>
                                <td style="padding: 0 40px 24px;">
                                    <h2 class="section-title">{{ __('messages.order_confirmation_mail_event_details') }}</h2>
                                    <p class="detail"><strong>{{ $event['name'] }}</strong></p>
                                    @if ($event['starts_at'])
                                        <p class="detail">{{ __('messages.order_confirmation_mail_starts', ['datetime' => $event['starts_at']]) }}</p>
                                    @endif
                                    @if ($event['ends_at'])
                                        <p class="detail">{{ __('messages.order_confirmation_mail_ends', ['datetime' => $event['ends_at']]) }}</p>
                                    @endif

                                    @if ($event['venue_name'] || $event['venue_address'])
                                        <h2 class="section-title" style="margin-top: 20px;">{{ __('messages.order_confirmation_mail_venue_details') }}</h2>
                                        @if ($event['venue_name'])
                                            <p class="detail"><strong>{{ $event['venue_name'] }}</strong></p>
                                        @endif
                                        @if ($event['venue_address'])
                                            <p class="detail">{{ $event['venue_address'] }}</p>
                                        @endif
                                    @endif

                                    @if ($event['organisation_name'])
                                        <h2 class="section-title" style="margin-top: 20px;">{{ __('messages.order_confirmation_mail_organisation_details') }}</h2>
                                        <p class="detail">{{ $event['organisation_name'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        <tr>
                            <td align="center" style="padding: 0 40px 28px;">
                                <p class="copy">{{ __('messages.order_confirmation_mail_thanks', ['name' => $customer_name]) }}</p>
                                <p class="muted" style="text-align: center; margin-top: 12px;">
                                    {{ __('messages.order_confirmation_mail_pdf_note') }}
                                </p>
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
                                <p class="footer" style="margin-top: 6px;">{{ __('messages.order_confirmation_mail_footer') }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
