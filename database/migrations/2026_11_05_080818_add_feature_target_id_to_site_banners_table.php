<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_banners')) {
            return;
        }

        if (Schema::hasColumn('site_banners', 'feature_target_id')) {
            return;
        }

        Schema::table('site_banners', function (Blueprint $table) {
            $table->unsignedBigInteger('feature_target_id')
                ->nullable()
                ->after('feature_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_banners')) {
            return;
        }

        if (! Schema::hasColumn('site_banners', 'feature_target_id')) {
            return;
        }

        Schema::table('site_banners', function (Blueprint $table) {
            $table->dropColumn('feature_target_id');
        });
    }
};
