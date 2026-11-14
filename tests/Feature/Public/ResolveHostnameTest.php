<?php

use App\Enum\EventStatusEnum;
use App\Enum\MediaTypeEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use App\Enum\SiteDomainStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\News;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteBanner;
use App\Models\SiteDomain;
use App\Models\SiteSupportContact;
use App\Services\MediaService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'hosts.marketplace' => ['yourlist.co.uk', 'www.yourlist.co.uk'],
        'hosts.equate_www' => true,
    ]);
});

test('marketplace hostname resolves as public without auth', function () {
    test()->getJson('/api/public/resolve-hostname?hostname=yourlist.co.uk')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_host_resolved'))
        ->assertJsonPath('data.host_type', 'public')
        ->assertJsonPath('data.hostname', 'yourlist.co.uk')
        ->assertJsonPath('data.organisation', null)
        ->assertJsonPath('data.site', null)
        ->assertJsonPath('data.domain', null)
        ->assertJsonPath('data.banners', []);
});

test('www marketplace host resolves as public when equate_www is enabled', function () {
    config(['hosts.marketplace' => ['yourlist.co.uk']]);

    test()->getJson('/api/public/resolve-hostname?hostname=www.yourlist.co.uk')
        ->assertOk()
        ->assertJsonPath('data.host_type', 'public');
});

test('published microsite hostname resolves with organisation and site', function () {
    $organisation = Organisation::factory()->create([
        'name' => 'Club Org',
        'logo' => 'orgs/logo.png',
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'slug' => 'club-site',
        'status' => SiteStatusEnum::ACTIVE,
        'logo' => 'sites/logo.png',
        'navigation' => [
            ['name' => 'Home', 'redirect_link' => '/'],
        ],
        'feature_object' => [
            'event_category' => 'on',
            'news_category' => 'off',
        ],
    ]);

    SiteDomain::query()->create([
        'site_id' => $site->id,
        'hostname' => 'club.example.com',
        'status' => SiteDomainStatusEnum::ACTIVE,
        'vercel_added' => true,
    ]);

    SiteSupportContact::query()->create([
        'site_id' => $site->id,
        'department_name' => 'Cleaning Department',
        'email' => 'cleaningsupport@yopmail.com',
        'phone' => '+44123412343',
        'status' => StatusEnum::ACTIVE,
        'show_in_footer' => true,
    ]);

    SiteSupportContact::query()->create([
        'site_id' => $site->id,
        'department_name' => 'Hidden Desk',
        'email' => 'hidden@yopmail.com',
        'phone' => '+44000000000',
        'status' => StatusEnum::ACTIVE,
        'show_in_footer' => false,
    ]);

    test()->getJson('/api/public/resolve-hostname?hostname=Club.Example.com')
        ->assertOk()
        ->assertJsonPath('data.host_type', 'microsite')
        ->assertJsonPath('data.hostname', 'club.example.com')
        ->assertJsonPath('data.organisation.id', $organisation->id)
        ->assertJsonPath('data.organisation.unique_id', $organisation->unique_id)
        ->assertJsonPath('data.organisation.name', 'Club Org')
        ->assertJsonPath('data.organisation.logo.path', 'orgs/logo.png')
        ->assertJsonPath('data.site.id', $site->id)
        ->assertJsonPath('data.site.logo.path', 'sites/logo.png')
        ->assertJsonPath('data.site.navigation.0.name', 'Home')
        ->assertJsonPath('data.site.feature_object.event_category', 'on')
        ->assertJsonPath('data.site.feature_object.news_category', 'off')
        ->assertJsonPath('data.site.feature_object.team_member_category', 'off')
        ->assertJsonPath('data.site.support_contact.department_name', 'Cleaning Department')
        ->assertJsonPath('data.site.support_contact.email', 'cleaningsupport@yopmail.com')
        ->assertJsonPath('data.site.support_contact.phone', '+44123412343')
        ->assertJsonPath('data.site.support_contact.show_in_footer', true)
        ->assertJsonPath('data.domain.hostname', 'club.example.com');
});

test('from_feature banner resolves event banner image and media_type', function () {
    $organisation = Organisation::factory()->create();

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    SiteDomain::query()->create([
        'site_id' => $site->id,
        'hostname' => 'banner.example.com',
        'status' => SiteDomainStatusEnum::ACTIVE,
        'vercel_added' => true,
    ]);

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Live Night',
        'starts_at' => Carbon::parse('2026-12-01 20:00:00'),
        'ends_at' => Carbon::parse('2026-12-01 23:00:00'),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $bannerPath = 'events/'.$event->id.'/banner.jpg';

    EventImage::query()->create([
        'event_id' => $event->id,
        'type' => 'banner',
        'media_type' => MediaTypeEnum::IMAGE,
        'path' => $bannerPath,
        'sort_order' => 0,
    ]);

    SiteBanner::query()->create([
        'site_id' => $site->id,
        'feature_type' => SiteBannerFeatureTypeEnum::EVENTS,
        'feature_target_id' => $event->id,
        'feature_image_target' => SiteBannerFeatureImageTargetEnum::FROM_FEATURE,
        'custom_headline' => null,
        'image_url' => null,
        'cta_button_1_label' => 'Get tickets',
        'cta_button_1_url' => '/events/'.$event->unique_id,
    ]);

    test()->getJson('/api/public/resolve-hostname?hostname=banner.example.com')
        ->assertOk()
        ->assertJsonPath('data.banners.0.feature_image_target', 'from_feature')
        ->assertJsonPath('data.banners.0.feature_type', 'events')
        ->assertJsonPath('data.banners.0.feature_target_id', $event->id)
        ->assertJsonPath('data.banners.0.image_url', app(MediaService::class)->url($bannerPath))
        ->assertJsonPath('data.banners.0.media_type', MediaTypeEnum::IMAGE->value);
});

test('from_feature banner resolves news featured image and media_type', function () {
    $organisation = Organisation::factory()->create();

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    SiteDomain::query()->create([
        'site_id' => $site->id,
        'hostname' => 'news-banner.example.com',
        'status' => SiteDomainStatusEnum::ACTIVE,
        'vercel_added' => true,
    ]);

    $featuredPath = 'news/featured/story.mp4';

    $news = News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'featured_image' => $featuredPath,
        'headline' => 'Club news',
        'slug' => 'club-news',
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    SiteBanner::query()->create([
        'site_id' => $site->id,
        'feature_type' => SiteBannerFeatureTypeEnum::NEWS,
        'feature_target_id' => $news->id,
        'feature_image_target' => SiteBannerFeatureImageTargetEnum::FROM_FEATURE,
        'image_url' => null,
        'cta_button_1_label' => 'Read more',
        'cta_button_1_url' => '/news/'.$news->slug,
    ]);

    test()->getJson('/api/public/resolve-hostname?hostname=news-banner.example.com')
        ->assertOk()
        ->assertJsonPath('data.banners.0.feature_type', 'news')
        ->assertJsonPath('data.banners.0.image_url', app(MediaService::class)->url($featuredPath))
        ->assertJsonPath('data.banners.0.media_type', MediaTypeEnum::VIDEO->value);
});

test('unknown hostname returns 404', function () {
    test()->getJson('/api/public/resolve-hostname?hostname=unknown.example.com')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.public_host_not_found'));
});

test('draft site hostname returns 404', function () {
    $organisation = Organisation::factory()->create();

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Draft Site',
        'status' => SiteStatusEnum::DRAFT,
    ]);

    SiteDomain::query()->create([
        'site_id' => $site->id,
        'hostname' => 'draft.example.com',
        'status' => SiteDomainStatusEnum::PENDING_DNS,
        'vercel_added' => false,
    ]);

    test()->getJson('/api/public/resolve-hostname?hostname=draft.example.com')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.public_host_not_found'));
});

test('missing hostname returns validation error', function () {
    test()->getJson('/api/public/resolve-hostname')
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The hostname field is required.');
});
