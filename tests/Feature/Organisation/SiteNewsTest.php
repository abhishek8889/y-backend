<?php

use App\Enum\NewsStatusEnum;
use App\Enum\SiteStatusEnum;
use App\Models\News;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Site, 2: string}
 */
function siteNewsFixture(): array
{
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Club Site',
        'status' => SiteStatusEnum::DRAFT,
        'created_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    return [$user, $site, $token];
}

test('organisation can create news with author content_role and schedule it', function () {
    [, $site, $token] = siteNewsFixture();

    $scheduledTo = Carbon::now()->addDay()->toIso8601String();

    $create = test()->withToken($token)
        ->postJson('/api/organisation/site/news/create', [
            'site_id' => $site->id,
            'author' => 'Jane Editor',
            'content_role' => 'announcement',
            'headline' => 'Season Kickoff',
            'excerpt' => 'We start next week.',
            'status' => NewsStatusEnum::SCHEDULED->value,
            'scheduled_to' => $scheduledTo,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.news.author', 'Jane Editor')
        ->assertJsonPath('data.news.content_role', 'announcement')
        ->assertJsonPath('data.news.status', NewsStatusEnum::SCHEDULED->value)
        ->assertJsonPath('data.news.headline', 'Season Kickoff');

    $newsId = (int) $create->json('data.news.id');
    $publishedAt = $create->json('data.news.published_at');
    $scheduledEcho = $create->json('data.news.scheduled_to');

    expect($publishedAt)->not->toBeNull()
        ->and($scheduledEcho)->toBe($publishedAt);

    $news = News::query()->findOrFail($newsId);
    expect($news->status)->toBe(NewsStatusEnum::SCHEDULED)
        ->and($news->author)->toBe('Jane Editor')
        ->and($news->content_role)->toBe('announcement')
        ->and($news->published_at)->not->toBeNull();
});

test('manual publish sets status published and published_at to now', function () {
    [, $site, $token] = siteNewsFixture();

    $create = test()->withToken($token)
        ->postJson('/api/organisation/site/news/create', [
            'site_id' => $site->id,
            'headline' => 'Draft Piece',
            'status' => NewsStatusEnum::DRAFT->value,
        ])
        ->assertOk();

    $newsId = (int) $create->json('data.news.id');

    $response = test()->withToken($token)
        ->postJson('/api/organisation/site/news/manage-status/'.$newsId, [
            'status' => NewsStatusEnum::PUBLISHED->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.news.status', NewsStatusEnum::PUBLISHED->value)
        ->assertJsonPath('data.news.scheduled_to', null);

    expect($response->json('data.news.published_at'))->not->toBeNull();
    expect(News::query()->findOrFail($newsId)->status)->toBe(NewsStatusEnum::PUBLISHED);
});

test('news:publish-due flips due scheduled items to published', function () {
    [, $site] = siteNewsFixture();

    $due = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Due Now',
        'slug' => 'due-now',
        'status' => NewsStatusEnum::SCHEDULED,
        'published_at' => now()->subMinute(),
        'author' => 'Bot',
        'content_role' => 'recap',
    ]);

    $future = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Later',
        'slug' => 'later',
        'status' => NewsStatusEnum::SCHEDULED,
        'published_at' => now()->addHour(),
    ]);

    $this->artisan('news:publish-due')->assertSuccessful();

    expect($due->fresh()->status)->toBe(NewsStatusEnum::PUBLISHED)
        ->and($future->fresh()->status)->toBe(NewsStatusEnum::SCHEDULED);
});

test('publiclyVisible includes published and due scheduled news', function () {
    [, $site] = siteNewsFixture();

    $live = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Live',
        'slug' => 'live',
        'status' => NewsStatusEnum::PUBLISHED,
        'published_at' => now()->subHour(),
    ]);

    $dueScheduled = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Due Scheduled',
        'slug' => 'due-scheduled',
        'status' => NewsStatusEnum::SCHEDULED,
        'published_at' => now()->subMinute(),
    ]);

    $future = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Future',
        'slug' => 'future',
        'status' => NewsStatusEnum::SCHEDULED,
        'published_at' => now()->addDay(),
    ]);

    $draft = News::query()->create([
        'organisation_id' => $site->organisation_id,
        'site_id' => $site->id,
        'headline' => 'Draft',
        'slug' => 'draft',
        'status' => NewsStatusEnum::DRAFT,
        'published_at' => null,
    ]);

    $ids = News::query()
        ->where('site_id', $site->id)
        ->publiclyVisible()
        ->pluck('id')
        ->all();

    expect($ids)->toContain($live->id, $dueScheduled->id)
        ->and($ids)->not->toContain($future->id, $draft->id);
});
