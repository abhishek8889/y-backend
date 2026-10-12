<?php

use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->string('provider', 30)->default(PaymentProviderEnum::STRIPE->value);
            $table->string('payment_intent_id')->nullable();
            $table->string('checkout_session_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->string('status', 30)->default(PaymentStatusEnum::PENDING->value);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('payment_method', 50)->nullable();
            $table->string('idempotency_key');
            $table->text('failure_reason')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique('payment_intent_id');
            $table->unique('idempotency_key');
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
