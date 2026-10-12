<?php

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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->foreignId('event_ticket_id')
                ->constrained('event_tickets')
                ->restrictOnDelete();
            $table->foreignId('ticket_offer_id')
                ->nullable()
                ->constrained('event_ticket_offers')
                ->nullOnDelete();
            $table->string('ticket_name');
            $table->string('offer_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->decimal('platform_fee_rate', 5, 2)->default(0);
            $table->decimal('platform_share_amount', 12, 2)->default(0);
            $table->decimal('organiser_share_amount', 12, 2)->default(0);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['event_id', 'event_ticket_id']);
            $table->index(['organisation_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
