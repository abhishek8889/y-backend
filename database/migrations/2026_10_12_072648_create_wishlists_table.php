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
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('organisation_id')
                ->nullable()
                ->constrained('organisations')
                ->nullOnDelete();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['organisation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
