<?php

namespace App\Jobs;

use App\Services\OrderConfirmationMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $orderId,
    ) {
        $this->afterCommit();
    }

    public function handle(OrderConfirmationMailService $orderConfirmations): void
    {
        $orderConfirmations->deliverForPaidOrder($this->orderId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('stripe_webhook')->error('Order confirmation email job failed permanently', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
