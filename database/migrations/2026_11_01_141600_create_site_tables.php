<?php

use App\Enum\NewsStatusEnum;
use App\Enum\SiteBannerStatusEnum;
use App\Enum\SiteBannerTypeEnum;
use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
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

            $table->string('site_type');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default(SiteStatusEnum::DRAFT->value);
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->json('header')->nullable();
            $table->json('footer')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
            $table->index(['organisation_id', 'site_type']);
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

            $table->string('banner_type')->default(SiteBannerTypeEnum::IMAGE->value);
            $table->string('banner_url')->nullable();
            $table->string('banner_title')->nullable();
            $table->string('banner_subtitle')->nullable();
            $table->string('cta_button_1_label')->nullable();
            $table->string('cta_button_1_url')->nullable();
            $table->string('cta_button_2_label')->nullable();
            $table->string('cta_button_2_url')->nullable();
            $table->string('status')->default(SiteBannerStatusEnum::DRAFT->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('page_name');
            $table->string('page_slug');
            $table->string('featured_image_url')->nullable();
            $table->json('content')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['site_id', 'page_name']);
            $table->unique(['site_id', 'page_slug']);
        });

        Schema::create('site_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'event_id']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();

            $table->string('unique_id')->unique();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default(NewsStatusEnum::DRAFT->value);
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('site_news', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->foreignId('news_id')
                ->constrained('news')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'news_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_news');
        Schema::dropIfExists('news');
        Schema::dropIfExists('site_events');
        Schema::dropIfExists('site_contents');
        Schema::dropIfExists('site_banners');
        Schema::dropIfExists('site_header_footers');
        Schema::dropIfExists('site_domains');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('site_templates');
    }
};
