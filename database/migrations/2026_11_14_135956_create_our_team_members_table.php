<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('our_team_members')) {
            return;
        }

        Schema::create('our_team_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_unique_id')->unique();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('image_url')->nullable();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->text('about')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->index(['site_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('our_team_members');
    }
};
