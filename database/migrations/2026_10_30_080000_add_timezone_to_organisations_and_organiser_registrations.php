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
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('timezone', 100)
                ->nullable()
                ->after('country');
        });

        Schema::table('organiser_registrations', function (Blueprint $table) {
            $table->string('org_timezone', 100)
                ->nullable()
                ->after('org_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('organiser_registrations', function (Blueprint $table) {
            $table->dropColumn('org_timezone');
        });
    }
};
