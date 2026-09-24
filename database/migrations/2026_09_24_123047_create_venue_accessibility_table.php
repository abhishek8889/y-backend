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
        Schema::create('venue_accessibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')
                ->unique()
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->boolean('accessible_entrance')->nullable();
            $table->boolean('accessible_toilet')->nullable();
            $table->boolean('wheelchair_access')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_accessibility');
    }
};
