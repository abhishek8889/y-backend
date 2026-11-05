<?php

use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
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

        if (! Schema::hasColumn('site_banners', 'feature_type')) {
            Schema::table('site_banners', function (Blueprint $table) {
                $column = $table->string('feature_type')
                    ->default(SiteBannerFeatureTypeEnum::CUSTOM->value);

                if (Schema::hasColumn('site_banners', 'site_id')) {
                    $column->after('site_id');
                }
            });
        }

        if (! Schema::hasColumn('site_banners', 'feature_target_id')) {
            Schema::table('site_banners', function (Blueprint $table) {
                $column = $table->unsignedBigInteger('feature_target_id')->nullable();

                if (Schema::hasColumn('site_banners', 'feature_type')) {
                    $column->after('feature_type');
                } elseif (Schema::hasColumn('site_banners', 'site_id')) {
                    $column->after('site_id');
                }
            });
        }

        if (! Schema::hasColumn('site_banners', 'feature_image_target')) {
            Schema::table('site_banners', function (Blueprint $table) {
                $column = $table->string('feature_image_target')
                    ->default(SiteBannerFeatureImageTargetEnum::CUSTOM_UPLOAD->value);

                if (Schema::hasColumn('site_banners', 'feature_target_id')) {
                    $column->after('feature_target_id');
                } elseif (Schema::hasColumn('site_banners', 'feature_type')) {
                    $column->after('feature_type');
                }
            });
        }

        if (! $this->siteBannersIndexExists('site_banners_site_id_feature_type_index')
            && Schema::hasColumn('site_banners', 'site_id')
            && Schema::hasColumn('site_banners', 'feature_type')
        ) {
            Schema::table('site_banners', function (Blueprint $table) {
                $table->index(['site_id', 'feature_type']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_banners')) {
            return;
        }

        if (Schema::hasColumn('site_banners', 'feature_target_id')) {
            Schema::table('site_banners', function (Blueprint $table) {
                $table->dropColumn('feature_target_id');
            });
        }
    }

    private function siteBannersIndexExists(string $indexName): bool
    {
        foreach (Schema::getIndexes('site_banners') as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
