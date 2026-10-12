<?php

use App\Enum\PaymentProviderEnum;
use App\Enum\WebhookEventStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default(PaymentProviderEnum::STRIPE->value);
            $table->string('event_id');
            $table->string('event_type', 150);
            $table->json('payload')->nullable();
            $table->string('status', 30)->default(WebhookEventStatusEnum::PENDING->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
