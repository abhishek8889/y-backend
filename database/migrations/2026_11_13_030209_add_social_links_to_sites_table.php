<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        if (Schema::hasColumn('sites', 'social_links')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->json('social_links')
                ->nullable()
                ->after('feature_object')
                ->comment('Social platform map, e.g. {"facebook":"https://...","instagram":"https://..."}');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        if (! Schema::hasColumn('sites', 'social_links')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
