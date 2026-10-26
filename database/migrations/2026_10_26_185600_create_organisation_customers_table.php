<?php

use App\Enum\StatusEnum;
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
        Schema::create('organisation_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('first_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('last_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->string('status', 30)->default(StatusEnum::ACTIVE->value);
            $table->dateTime('status_updated_at')->nullable();
            $table->string('status_updated_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_customers');
    }
};
