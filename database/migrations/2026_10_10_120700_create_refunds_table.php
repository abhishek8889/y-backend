<?php

use App\Enum\RefundStatusEnum;
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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id', 26)->unique();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();
            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('stripe_refund_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('reason', 100)->nullable();
            $table->string('status', 30)->default(RefundStatusEnum::PENDING->value);
            $table->text('failure_reason')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();

            $table->unique('stripe_refund_id');
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
