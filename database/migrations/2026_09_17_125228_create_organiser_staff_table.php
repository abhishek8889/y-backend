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
        Schema::create('organiser_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('status', [StatusEnum::ACTIVE->value, StatusEnum::INACTIVE->value])
                ->default(StatusEnum::ACTIVE->value);

            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organiser_staff');
    }
};
