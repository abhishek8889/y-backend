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

        Schema::table('news', function (Blueprint $table) {
            if (! Schema::hasColumn('news', 'author')) {
                $table->string('author', 255)->nullable()->after('featured_image');
            }

            if (! Schema::hasColumn('news', 'content_role')) {
                $table->text('content_role')
                    ->nullable()
                    ->after('author')
                    ->comment('e.g. announcement, recap');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        Schema::table('news', function (Blueprint $table) {
            if (Schema::hasColumn('news', 'content_role')) {
                $table->dropColumn('content_role');
            }

            if (Schema::hasColumn('news', 'author')) {
                $table->dropColumn('author');
            }
        });
    }
};
