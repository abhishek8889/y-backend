<?php

use App\Enum\IssuedTicketStatusEnum;
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
        Schema::create('issued_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id', 26)->unique();
            $table->string('code', 64)->unique();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->restrictOnDelete();
            $table->foreignId('event_id')
                ->constrained('events')
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
            $table->string('status', 30)->default(IssuedTicketStatusEnum::VALID->value);
            $table->dateTime('status_updated_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['organisation_id', 'event_id']);
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issued_tickets');
    }
};
