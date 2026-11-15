<?php

use App\Enum\EventStatusEnum;
use App\Enum\MediaTypeEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteCategoryTypeEnum;
use App\Enum\SiteStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventImage;
use App\Models\News;
use App\Models\Organisation;
use App\Models\OurTeamMember;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\SiteSupportContact;
use App\Models\Venue;
use App\Models\VenueAddress;
use App\Models\VenueType;
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

test('public get-all-news lists published news for the site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $published = News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'headline' => 'Published Story',
        'slug' => 'published-story',
        'excerpt' => 'Hello world',
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'headline' => 'Draft Story',
        'slug' => 'draft-story',
        'status' => NewsStatusEnum::DRAFT,
    ]);

    test()->getJson('/api/public/get-all-news?site_unique_id='.$site->unique_id.'&search=Published')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_all_news'))
        ->assertJsonPath('data.items.0.id', $published->id)
        ->assertJsonPath('data.items.0.headline', 'Published Story');

    $ids = collect(test()->getJson('/api/public/get-all-news?site_unique_id='.$site->unique_id)
        ->json('data.items'))->pluck('id');

    expect($ids)->toContain($published->id)->toHaveCount(1);
});

test('public our team members lists members for the site', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $member = OurTeamMember::query()->create([
        'member_unique_id' => UniqueIdGenerator::generate('OTM'),
        'site_id' => $site->id,
        'name' => 'Alex Coach',
        'designation' => 'Head Coach',
        'about' => 'Leads the senior team.',
        'joined_at' => '2024-01-15',
    ]);

    test()->getJson('/api/public/get-our-team-members?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_our_team_members'))
        ->assertJsonPath('data.items.0.id', $member->id)
        ->assertJsonPath('data.items.0.member_unique_id', $member->member_unique_id)
        ->assertJsonPath('data.items.0.name', 'Alex Coach')
        ->assertJsonPath('data.items.0.designation', 'Head Coach');
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

test('public get-news-detail returns news with linked event banner and dates', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $category = EventCategory::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Football',
        'slug' => 'football',
    ]);

    $venueType = VenueType::query()->create([
        'name' => 'Stadium',
        'slug' => 'stadium',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Wembley',
        'description' => 'National stadium',
        'venue_type_id' => $venueType->id,
        'status' => 'active',
    ]);

    VenueAddress::query()->create([
        'venue_id' => $venue->id,
        'address_line_1' => 'Wembley Way',
        'city' => 'London',
        'state' => 'Greater London',
        'postal_code' => 'HA9 0WS',
        'country' => 'United Kingdom',
    ]);

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Cup Final',
        'description' => 'The biggest match of the year.',
        'event_category_id' => $category->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-12-01 20:00:00'),
        'ends_at' => Carbon::parse('2026-12-01 23:00:00'),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    EventImage::query()->create([
        'event_id' => $event->id,
        'type' => 'banner',
        'media_type' => MediaTypeEnum::IMAGE,
        'path' => 'events/'.$event->id.'/banner.jpg',
        'sort_order' => 0,
    ]);

    News::query()->create([
        'organisation_id' => $organisation->id,
        'site_id' => $site->id,
        'author' => 'Jane Editor',
        'content_role' => 'recap',
        'headline' => 'Cup Final Recap',
        'slug' => 'cup-final-recap',
        'excerpt' => 'What a night.',
        'description' => 'Full story body.',
        'linked_event_id' => $event->id,
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    test()->getJson('/api/public/get-news-detail/cup-final-recap?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_news_detail'))
        ->assertJsonPath('data.news.slug', 'cup-final-recap')
        ->assertJsonPath('data.news.author', 'Jane Editor')
        ->assertJsonPath('data.news.content_role', 'recap')
        ->assertJsonPath('data.news.headline', 'Cup Final Recap')
        ->assertJsonPath('data.news.description', 'Full story body.')
        ->assertJsonPath('data.news.linked_event.unique_id', $event->unique_id)
        ->assertJsonPath('data.news.linked_event.name', 'Cup Final')
        ->assertJsonPath('data.news.linked_event.description', 'The biggest match of the year.')
        ->assertJsonPath('data.news.linked_event.timezone', 'Europe/London')
        ->assertJsonPath('data.news.linked_event.category.name', 'Football')
        ->assertJsonPath('data.news.linked_event.category.slug', 'football')
        ->assertJsonPath('data.news.linked_event.venue.unique_id', $venue->unique_id)
        ->assertJsonPath('data.news.linked_event.venue.name', 'Wembley')
        ->assertJsonPath('data.news.linked_event.venue.description', 'National stadium')
        ->assertJsonPath('data.news.linked_event.venue.venue_type.name', 'Stadium')
        ->assertJsonPath('data.news.linked_event.venue.address.city', 'London')
        ->assertJsonPath('data.news.linked_event.images.0.path', 'events/'.$event->id.'/banner.jpg');

    test()->getJson('/api/public/get-news-detail/cup-final-recap')
        ->assertUnprocessable();

    test()->getJson('/api/public/get-news-detail/missing-slug?site_unique_id='.$site->unique_id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.site_news_not_found'));
});

test('public get-venue-detail returns venue for the site organisation', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $venueType = VenueType::query()->create([
        'name' => 'Arena',
        'slug' => 'arena',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Main Arena',
        'description' => 'Home ground',
        'venue_type_id' => $venueType->id,
        'status' => StatusEnum::ACTIVE->value,
    ]);

    VenueAddress::query()->create([
        'venue_id' => $venue->id,
        'address_line_1' => '1 Arena Road',
        'city' => 'Manchester',
        'state' => 'Greater Manchester',
        'postal_code' => 'M1 1AA',
        'country' => 'United Kingdom',
    ]);

    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Inactive Hall',
        'status' => StatusEnum::INACTIVE->value,
    ]);

    test()->getJson('/api/public/get-venue-detail/'.$venue->unique_id.'?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_venue_detail'))
        ->assertJsonPath('data.venue.unique_id', $venue->unique_id)
        ->assertJsonPath('data.venue.name', 'Main Arena')
        ->assertJsonPath('data.venue.description', 'Home ground')
        ->assertJsonPath('data.venue.venue_type.name', 'Arena')
        ->assertJsonPath('data.venue.address.city', 'Manchester');

    // Marketplace: site_unique_id omitted
    test()->getJson('/api/public/get-venue-detail/'.$venue->unique_id)
        ->assertOk()
        ->assertJsonPath('data.venue.unique_id', $venue->unique_id)
        ->assertJsonPath('data.venue.name', 'Main Arena');

    test()->getJson('/api/public/get-venue-detail/VENXXXX?site_unique_id='.$site->unique_id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_not_found'));
});

test('public team-members detail returns member by member_unique_id', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::ACTIVE,
    ]);

    $member = OurTeamMember::query()->create([
        'member_unique_id' => UniqueIdGenerator::generate('OTM'),
        'site_id' => $site->id,
        'name' => 'Alex Coach',
        'designation' => 'Head Coach',
        'about' => 'Leads the senior team.',
        'joined_at' => '2024-01-15',
    ]);

    test()->getJson('/api/public/team-members/detail/'.$member->member_unique_id.'?site_unique_id='.$site->unique_id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_our_team_member_detail'))
        ->assertJsonPath('data.member.member_unique_id', $member->member_unique_id)
        ->assertJsonPath('data.member.name', 'Alex Coach')
        ->assertJsonPath('data.member.designation', 'Head Coach')
        ->assertJsonPath('data.member.about', 'Leads the senior team.');

    test()->getJson('/api/public/team-members/detail/'.$member->member_unique_id)
        ->assertUnprocessable();

    test()->getJson('/api/public/team-members/detail/OTMXXXX?site_unique_id='.$site->unique_id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.our_team_member_not_found'));
});
