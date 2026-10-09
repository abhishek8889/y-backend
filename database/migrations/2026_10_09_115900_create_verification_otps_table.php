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
        Schema::create('verification_otps', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('otp');
            $table->timestamp('expire_at');
            $table->timestamps();

            $table->index(['type', 'email', 'phone']);
            $table->index('expire_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_otps');
    }
};
