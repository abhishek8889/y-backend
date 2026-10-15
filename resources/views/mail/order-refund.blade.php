<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.order_refund_mail_subject', ['order' => $order_unique_id]) }}</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            background-color: #f2f2f2;
            font-family: Univers, 'Univers LT Std', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #000000;
        }

        .card {
            max-width: 600px;
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

        .section-title {
            margin: 24px 0 12px;
            font-size: 16px;
            font-weight: 700;
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
            margin: 16px 0 0;
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
            color: #666666;
        }

        .ticket-block {
            margin: 0 0 16px;
            padding: 12px 0;
            border-top: 1px solid #ececec;
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
            <h1 class="heading">{{ __('messages.order_refund_mail_heading') }}</h1>
            <p class="copy">
                {{ __('messages.order_refund_mail_intro', ['name' => $customer_name]) }}
            </p>
            <p class="copy">
                {{ __('messages.order_refund_mail_body', ['days' => $expected_days]) }}
            </p>

            <h2 class="section-title">{{ __('messages.order_refund_mail_refund_details') }}</h2>
            <p class="detail">{{ __('messages.order_refund_mail_order_id', ['id' => $order_id]) }}</p>
            <p class="detail">{{ __('messages.order_refund_mail_order_ref', ['order' => $order_unique_id]) }}</p>
            <p class="detail">{{ __('messages.order_refund_mail_refund_ref', ['refund' => $refund_unique_id]) }}</p>
            <p class="detail">{{ __('messages.order_refund_mail_amount', ['amount' => $formatted_refund_amount]) }}</p>
            @if (filled($refund_reason))
                <p class="detail">{{ __('messages.order_refund_mail_reason', ['reason' => $refund_reason]) }}</p>
            @endif

            <h2 class="section-title">{{ __('messages.order_refund_mail_ticket_details') }}</h2>
            @foreach ($tickets as $ticket)
                <div class="ticket-block">
                    <p class="detail">{{ __('messages.order_refund_mail_ticket_name', ['name' => $ticket['ticket_name']]) }}</p>
                    <p class="detail">{{ __('messages.order_refund_mail_ticket_id', ['id' => $ticket['ticket_id']]) }}</p>
                    <p class="detail">{{ __('messages.order_refund_mail_event_name', ['name' => $ticket['event_name']]) }}</p>
                    <p class="detail">{{ __('messages.order_refund_mail_event_id', ['id' => $ticket['event_id']]) }}</p>
                    @if (! empty($ticket['quantity']))
                        <p class="detail">{{ __('messages.order_refund_mail_quantity', ['qty' => $ticket['quantity']]) }}</p>
                    @endif
                </div>
            @endforeach

            <p class="muted">{{ __('messages.order_refund_mail_footer') }}</p>
        </div>
    </div>
</body>
</html>
