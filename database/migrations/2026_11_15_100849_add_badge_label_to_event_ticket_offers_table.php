<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_ticket_offers')) {
            return;
        }

        Schema::table('event_ticket_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('event_ticket_offers', 'badge_label')) {
                $table->string('badge_label', 255)
                    ->nullable()
                    ->after('name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_ticket_offers')) {
            return;
        }

        Schema::table('event_ticket_offers', function (Blueprint $table) {
            if (Schema::hasColumn('event_ticket_offers', 'badge_label')) {
                $table->dropColumn('badge_label');
            }
        });
    }
};
