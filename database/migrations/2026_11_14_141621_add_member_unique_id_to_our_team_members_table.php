<?php

use App\Support\UniqueIdGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('our_team_members')) {
            return;
        }

        if (! Schema::hasColumn('our_team_members', 'member_unique_id')) {
            Schema::table('our_team_members', function (Blueprint $table) {
                $table->string('member_unique_id')->nullable()->after('id');
            });
        }

        $rows = DB::table('our_team_members')
            ->whereNull('member_unique_id')
            ->orWhere('member_unique_id', '')
            ->get(['id']);

        foreach ($rows as $row) {
            $uniqueId = UniqueIdGenerator::generate('OTM');

            while (
                DB::table('our_team_members')
                    ->where('member_unique_id', $uniqueId)
                    ->exists()
            ) {
                $uniqueId = UniqueIdGenerator::generate('OTM');
            }

            DB::table('our_team_members')
                ->where('id', $row->id)
                ->update(['member_unique_id' => $uniqueId]);
        }

        // Ensure uniqueness index exists after backfill.
        $indexes = Schema::getIndexes('our_team_members');
        $hasUnique = false;
        foreach ($indexes as $index) {
            if (($index['name'] ?? null) === 'our_team_members_member_unique_id_unique') {
                $hasUnique = true;
                break;
            }
        }

        if (! $hasUnique) {
            Schema::table('our_team_members', function (Blueprint $table) {
                $table->unique('member_unique_id');
            });
        }

        // Keep column non-nullable going forward (MySQL).
        Schema::table('our_team_members', function (Blueprint $table) {
            $table->string('member_unique_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('our_team_members')) {
            return;
        }

        if (! Schema::hasColumn('our_team_members', 'member_unique_id')) {
            return;
        }

        Schema::table('our_team_members', function (Blueprint $table) {
            $table->dropUnique(['member_unique_id']);
            $table->dropColumn('member_unique_id');
        });
    }
};
