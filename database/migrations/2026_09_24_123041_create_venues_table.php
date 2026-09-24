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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();
            $table->string('unique_id')->unique();
            $table->string('name');
            $table->foreignId('venue_type_id')
                ->nullable()
                ->constrained('venue_types')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('maximum_capacity')->nullable();
            $table->unsignedInteger('standing_capacity')->nullable();
            $table->unsignedInteger('seated_capacity')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_private_hire_available')->default(false);
            $table->text('private_hire_description')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('venue_social_links');
        Schema::dropIfExists('venue_images');
        Schema::dropIfExists('venue_staff');
        Schema::dropIfExists('venue_contacts');
        Schema::dropIfExists('venue_addresses');

        Schema::dropIfExists('venues');

        Schema::enableForeignKeyConstraints();

    }
};
