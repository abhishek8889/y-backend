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
        Schema::create('venue_logistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')
                ->unique()
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->text('parking_information')->nullable();
            $table->text('public_transport_information')->nullable();
            $table->text('travel_information')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_logistics');
    }
};
