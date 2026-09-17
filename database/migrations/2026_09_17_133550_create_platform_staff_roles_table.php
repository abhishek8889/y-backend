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
        Schema::create('platform_staff_roles', function (Blueprint $table) {
            $table->foreignId('platform_staff_id')
                ->constrained('platform_staff')
                ->cascadeOnDelete();

            $table->foreignId('platform_role_id')
                ->constrained('platform_roles')
                ->cascadeOnDelete();

            $table->primary(['platform_staff_id', 'platform_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_staff_roles');
    }
};
