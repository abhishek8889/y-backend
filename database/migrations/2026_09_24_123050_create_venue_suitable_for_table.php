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
        Schema::create('venue_suitable_for', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->foreignId('suitable_for_option_id')
                ->constrained('venue_suitable_for_options')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['venue_id', 'suitable_for_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_suitable_for');
    }
};
