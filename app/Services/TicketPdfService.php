<?php

namespace App\Services;

use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Venue;
use App\Models\VenueAddress;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TicketPdfService extends Service
{
    /**
     * Generate (or reuse) a PDF containing every issued ticket and its scan QR.
     * QR payload is the issued ticket `code` used for gate scanning.
     *
     * @return array{path: string, absolute_path: string, filename: string}
     */
    public function generateForOrder(Order $order): array
    {
        $order->loadMissing([
            'issuedTickets.event.venue.address',
            'issuedTickets.organisation:id,name',
        ]);

        if ($order->issuedTickets->isEmpty()) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.order_tickets_pdf_missing_tickets'),
            );
        }

        $relativePath = $this->relativePathForOrder($order);

        if (filled($order->tickets_pdf_path) && Storage::disk('local')->exists($order->tickets_pdf_path)) {
            return [
                'path' => $order->tickets_pdf_path,
                'absolute_path' => Storage::disk('local')->path($order->tickets_pdf_path),
                'filename' => $this->downloadFilename($order),
            ];
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return [
                'path' => $relativePath,
                'absolute_path' => Storage::disk('local')->path($relativePath),
                'filename' => $this->downloadFilename($order),
            ];
        }

        $tickets = $order->issuedTickets->map(function (IssuedTicket $ticket): array {
            /** @var Event|null $event */
            $event = $ticket->event;
            /** @var Organisation|null $organisation */
            $organisation = $ticket->organisation;
            /** @var Venue|null $venue */
            $venue = $event?->venue;
            /** @var VenueAddress|null $address */
            $address = $venue?->address;
            $timezone = filled($event?->timezone) ? (string) $event->timezone : (string) config('app.timezone');

            return [
                'unique_id' => $ticket->unique_id,
                'code' => $ticket->code,
                'ticket_name' => $ticket->ticket_name,
                'offer_name' => $ticket->offer_name,
                'event_name' => (string) ($event?->name ?? __('messages.order_confirmation_unknown_event')),
                'starts_at' => $this->formatEventDateTime($event?->starts_at, $timezone),
                'ends_at' => $this->formatEventDateTime($event?->ends_at, $timezone),
                'venue_name' => $venue?->name,
                'venue_address' => $this->formatVenueAddress($address),
                'organisation_name' => $organisation?->name,
                'qr_src' => $this->qrDataUriForCode($ticket->code),
            ];
        })->values()->all();

        $pdf = Pdf::loadView('pdf.tickets', [
            'order' => $order,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'order_unique_id' => $order->unique_id,
            'tickets' => $tickets,
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true);

        Storage::disk('local')->put($relativePath, $pdf->output());

        $order->forceFill([
            'tickets_pdf_path' => $relativePath,
        ])->save();

        return [
            'path' => $relativePath,
            'absolute_path' => Storage::disk('local')->path($relativePath),
            'filename' => $this->downloadFilename($order),
        ];
    }

    private function relativePathForOrder(Order $order): string
    {
        return 'tickets/orders/'.$order->unique_id.'.pdf';
    }

    private function downloadFilename(Order $order): string
    {
        return 'yourlist-tickets-'.$order->unique_id.'.pdf';
    }

    /**
     * Encode the gate scan token as a PNG data URI.
     * DomPDF does not reliably render Bacon's path-based SVG output.
     */
    private function qrDataUriForCode(string $code): string
    {
        $png = (new Writer(new GDLibRenderer(220)))->writeString($code);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function formatEventDateTime(?CarbonInterface $dateTime, string $timezone): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()
            ->timezone($timezone)
            ->format('l, j F Y \a\t g:i A T');
    }

    private function formatVenueAddress(?VenueAddress $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $parts = array_values(array_filter([
            $address->address_line_1,
            $address->address_line_2,
            $address->city,
            $address->state,
            $address->postal_code,
            $address->country,
        ], fn (?string $part): bool => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
