<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tickets – {{ $order_unique_id }}</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12px;
            line-height: 1.4;
        }
        .ticket {
            border: 1px solid #d0d0d0;
            padding: 18px 20px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .brand {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .event-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 6px;
        }
        .meta { margin: 0 0 4px; color: #333; }
        .label { color: #666; }
        .grid {
            width: 100%;
            margin-top: 12px;
        }
        .grid td { vertical-align: top; }
        .qr {
            width: 160px;
            text-align: center;
        }
        .qr img {
            width: 140px;
            height: 140px;
        }
        .code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            word-break: break-all;
            margin-top: 6px;
        }
        .footer {
            margin-top: 10px;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <p class="brand">yourlist</p>
    <p class="meta"><span class="label">Order:</span> {{ $order_unique_id }}</p>
    <p class="meta"><span class="label">Customer:</span> {{ $customer_name }} ({{ $customer_email }})</p>

    @foreach ($tickets as $ticket)
        <div class="ticket">
            <table class="grid" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 70%; padding-right: 12px;">
                        <p class="event-name">{{ $ticket['event_name'] }}</p>
                        @if ($ticket['starts_at'])
                            <p class="meta"><span class="label">Starts:</span> {{ $ticket['starts_at'] }}</p>
                        @endif
                        @if ($ticket['ends_at'])
                            <p class="meta"><span class="label">Ends:</span> {{ $ticket['ends_at'] }}</p>
                        @endif
                        @if ($ticket['venue_name'])
                            <p class="meta"><span class="label">Venue:</span> {{ $ticket['venue_name'] }}</p>
                        @endif
                        @if ($ticket['venue_address'])
                            <p class="meta">{{ $ticket['venue_address'] }}</p>
                        @endif
                        <p class="meta"><span class="label">Ticket:</span> {{ $ticket['ticket_name'] }}</p>
                        @if ($ticket['offer_name'])
                            <p class="meta"><span class="label">Offer:</span> {{ $ticket['offer_name'] }}</p>
                        @endif
                        <p class="meta"><span class="label">Ticket ID:</span> {{ $ticket['unique_id'] }}</p>
                        @if ($ticket['organisation_name'])
                            <p class="meta"><span class="label">Organisation:</span> {{ $ticket['organisation_name'] }}</p>
                        @endif
                    </td>
                    <td class="qr">
                        <img src="{{ $ticket['qr_src'] }}" width="140" height="140" alt="Ticket QR">
                        <div class="code">{{ $ticket['code'] }}</div>
                    </td>
                </tr>
            </table>
            <p class="footer">Present this QR code at the gate. Each code admits one person.</p>
        </div>
    @endforeach
</body>
</html>
