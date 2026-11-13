<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_support_contacts')) {
            return;
        }

        if (Schema::hasColumn('site_support_contacts', 'show_in_footer')) {
            return;
        }

        Schema::table('site_support_contacts', function (Blueprint $table) {
            $table->boolean('show_in_footer')
                ->default(false)
                ->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_support_contacts')) {
            return;
        }

        if (! Schema::hasColumn('site_support_contacts', 'show_in_footer')) {
            return;
        }

        Schema::table('site_support_contacts', function (Blueprint $table) {
            $table->dropColumn('show_in_footer');
        });
    }
};
