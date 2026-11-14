<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('our_team_members')) {
            return;
        }

        if (Schema::hasColumn('our_team_members', 'joined_at')) {
            return;
        }

        Schema::table('our_team_members', function (Blueprint $table) {
            $table->timestamp('joined_at')->nullable()->after('about');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('our_team_members')) {
            return;
        }

        if (! Schema::hasColumn('our_team_members', 'joined_at')) {
            return;
        }

        Schema::table('our_team_members', function (Blueprint $table) {
            $table->dropColumn('joined_at');
        });
    }
};
