<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        if (! Schema::hasColumn('news', 'site_id') && Schema::hasTable('sites')) {
            Schema::table('news', function (Blueprint $table) {
                $table->foreignId('site_id')
                    ->nullable()
                    ->after('organisation_id')
                    ->constrained('sites')
                    ->nullOnDelete();
            });

            if (! $this->newsIndexExists('news_site_id_status_index')) {
                Schema::table('news', function (Blueprint $table) {
                    $table->index(['site_id', 'status']);
                });
            }
        }

        if (Schema::hasColumn('news', 'featured_image')) {
            return;
        }

        Schema::table('news', function (Blueprint $table) {
            $column = $table->string('featured_image')->nullable();

            if (Schema::hasColumn('news', 'site_id')) {
                $column->after('site_id');
            } elseif (Schema::hasColumn('news', 'organisation_id')) {
                $column->after('organisation_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        if (Schema::hasColumn('news', 'featured_image')) {
            Schema::table('news', function (Blueprint $table) {
                $table->dropColumn('featured_image');
            });
        }
    }

    private function newsIndexExists(string $indexName): bool
    {
        foreach (Schema::getIndexes('news') as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
