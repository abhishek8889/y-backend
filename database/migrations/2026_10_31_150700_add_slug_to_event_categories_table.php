<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $categories = DB::table('event_categories')->orderBy('id')->get(['id', 'name']);
        $usedSlugs = [];

        foreach ($categories as $category) {
            $base = Str::slug((string) $category->name);
            if ($base === '') {
                $base = 'category';
            }

            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('event_categories')
                ->where('id', $category->id)
                ->update(['slug' => $slug]);
        }

        Schema::table('event_categories', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
