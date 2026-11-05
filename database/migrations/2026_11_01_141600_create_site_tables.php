<?php

use App\Enum\NewsStatusEnum;
use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Enum\StatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('site_type');
            $table->string('frontend_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('site_type');
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->unique();

            $table->foreignId('organisation_id')
                ->nullable()
                ->constrained('organisations')
                ->cascadeOnDelete();

            $table->foreignId('site_template_id')
                ->nullable()
                ->constrained('site_templates')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default(SiteStatusEnum::DRAFT->value);
            $table->string('logo')->nullable();
            $table->json('navigation')->nullable();
            $table->json('feature_object')->nullable()->comment(
                'Site feature toggles, e.g. {"event_category":"on","news_category":"on"}'
            );

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->unique()
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('hostname')->unique();
            $table->string('status')->default(SiteDomainStatusEnum::PENDING_DNS->value);
            $table->boolean('vercel_added')->default(false);
            $table->json('dns_instructions')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('feature_type')->default(SiteBannerFeatureTypeEnum::CUSTOM->value);
            $table->unsignedBigInteger('feature_target_id')->nullable()->after('feature_type');
            $table->string('feature_image_target')
                ->default(SiteBannerFeatureImageTargetEnum::CUSTOM_UPLOAD->value);
            $table->string('custom_headline')->nullable()->comment('Used when feature_type is custom');
            $table->string('image_url')->nullable()->comment(
                'Required when feature_image_target is custom_upload, or when feature_type is custom'
            );
            $table->string('cta_button_1_label')->nullable();
            $table->string('cta_button_1_url')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'feature_type']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            $table->string('headline');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('description')->nullable();

            $table->foreignId('linked_event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            $table->string('status')->default(NewsStatusEnum::DRAFT->value);
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
            $table->index(['organisation_id', 'status']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('site_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('category_type');
            $table->unsignedBigInteger('category_id')->comment(
                'Target id: event id when category_type=events, news id when category_type=news'
            );
            $table->timestamps();

            $table->unique(['site_id', 'category_type', 'category_id']);
            $table->index(['category_type', 'category_id']);
        });

        Schema::create('site_support_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('department_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default(StatusEnum::ACTIVE->value);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_support_contacts');
        Schema::dropIfExists('site_categories');
        Schema::dropIfExists('news');
        Schema::dropIfExists('site_banners');
        Schema::dropIfExists('site_domains');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('site_templates');
    }
};
