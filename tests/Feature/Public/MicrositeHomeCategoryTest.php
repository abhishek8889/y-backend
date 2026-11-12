<?php

use App\Enum\EventStatusEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteCategoryTypeEnum;
use App\Enum\SiteStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\News;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\SiteSupportContact;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('home category events lists published events selected for the site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $selected = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Selected Night',
        'starts_at' => Carbon::parse('2026-12-01 20:00:00'),
        'ends_at' => Carbon::parse('2026-12-01 23:00:00'),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $other = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Not Selected',
        'starts_at' => Carbon::parse('2026-12-02 20:00:00'),
        'ends_at' => Carbon::parse('2026-12-02 23:00:00'),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    SiteCategory::query()->create([
        'site_id' => $site->id,
        'category_type' => SiteCategoryTypeEnum::EVENTS,
        'category_id' => $selected->id,
    ]);

    test()->getJson('/api/public/get-home-category-events?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_home_category_events'))
        ->assertJsonPath('data.items.0.id', $selected->id)
        ->assertJsonPath('data.items.0.unique_id', $selected->unique_id)
        ->assertJsonPath('data.pagination.has_more', false);

    $ids = collect(test()->getJson('/api/public/get-home-category-events?site_unique_id='.$site->unique_id)
        ->json('data.items'))->pluck('id');

    expect($ids)->toContain($selected->id)->not->toContain($other->id);
});

test('home category news lists published news selected for the site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $selected = News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'headline' => 'Selected story',
        'slug' => 'selected-story',
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'headline' => 'Other story',
        'slug' => 'other-story',
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    SiteCategory::query()->create([
        'site_id' => $site->id,
        'category_type' => SiteCategoryTypeEnum::NEWS,
        'category_id' => $selected->id,
    ]);

    test()->getJson('/api/public/get-home-category-news?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_home_category_news'))
        ->assertJsonPath('data.items.0.id', $selected->id)
        ->assertJsonPath('data.items.0.headline', 'Selected story');
});

test('home category endpoints require site_unique_id', function () {
    test()->getJson('/api/public/get-home-category-events')
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    test()->getJson('/api/public/get-home-category-news')
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

test('support contacts lists active contacts for the site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $active = SiteSupportContact::query()->create([
        'site_id' => $site->id,
        'department_name' => 'Cleaning Department',
        'email' => 'cleaningsupport@yopmail.com',
        'phone' => '+44123412343',
        'status' => StatusEnum::ACTIVE,
    ]);

    SiteSupportContact::query()->create([
        'site_id' => $site->id,
        'department_name' => 'Inactive Desk',
        'email' => 'inactive@yopmail.com',
        'phone' => '+44000000000',
        'status' => StatusEnum::INACTIVE,
    ]);

    test()->getJson('/api/public/get-support-contacts?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_support_contacts'))
        ->assertJsonPath('data.items.0.id', $active->id)
        ->assertJsonPath('data.items.0.department_name', 'Cleaning Department')
        ->assertJsonPath('data.items.0.email', 'cleaningsupport@yopmail.com')
        ->assertJsonPath('data.items.0.phone', '+44123412343')
        ->assertJsonPath('data.pagination.has_more', false);

    $ids = collect(test()->getJson('/api/public/get-support-contacts?site_unique_id='.$site->unique_id)
        ->json('data.items'))->pluck('id');

    expect($ids)->toContain($active->id)->toHaveCount(1);
});

test('home category endpoints return 404 for unknown or draft site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $draft = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Draft Site',
        'status' => SiteStatusEnum::DRAFT,
    ]);

    test()->getJson('/api/public/get-home-category-events?site_unique_id='.$draft->unique_id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.site_not_found'));

    test()->getJson('/api/public/get-home-category-news?site_unique_id=DOESNOTEXIST')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.site_not_found'));
});
