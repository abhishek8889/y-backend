<?php

namespace App\Jobs;

use App\Services\OrderRefundMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderRefundEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $refundId,
    ) {
        $this->afterCommit();
    }

    public function handle(OrderRefundMailService $refundMail): void
    {
        $refundMail->deliverForSucceededRefund($this->refundId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Order refund email job failed permanently', [
            'refund_id' => $this->refundId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
