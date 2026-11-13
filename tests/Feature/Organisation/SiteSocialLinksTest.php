<?php

use App\Enum\SiteStatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Organisation, 2: Site, 3: string}
 */
function siteSocialLinksFixture(): array
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

    return [$user, $organisation, $site, $token];
}

test('organisation can save social links for a site', function () {
    [, , $site, $token] = siteSocialLinksFixture();

    test()->withToken($token)
        ->postJson('/api/organisation/site/social-links/save', [
            'site_id' => $site->id,
            'social_links' => [
                'Facebook' => 'https://facebook.com/club',
                'instagram' => 'https://instagram.com/club',
                'twitter' => '',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.site_social_links_saved'))
        ->assertJsonPath('data.social_links.facebook', 'https://facebook.com/club')
        ->assertJsonPath('data.social_links.instagram', 'https://instagram.com/club')
        ->assertJsonMissingPath('data.social_links.twitter');

    expect($site->fresh()->social_links)->toMatchArray([
        'facebook' => 'https://facebook.com/club',
        'instagram' => 'https://instagram.com/club',
    ]);
});

test('organisation cannot save social links twice', function () {
    [, , $site, $token] = siteSocialLinksFixture();

    $site->update([
        'social_links' => ['facebook' => 'https://facebook.com/old'],
    ]);

    test()->withToken($token)
        ->postJson('/api/organisation/site/social-links/save', [
            'site_id' => $site->id,
            'social_links' => [
                'instagram' => 'https://instagram.com/club',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.site_social_links_already_saved'));
});

test('organisation can update social links for a site', function () {
    [, , $site, $token] = siteSocialLinksFixture();

    $site->update([
        'social_links' => ['facebook' => 'https://facebook.com/old'],
    ]);

    test()->withToken($token)
        ->postJson('/api/organisation/site/social-links/update', [
            'site_id' => $site->id,
            'social_links' => [
                'instagram' => 'https://instagram.com/club',
                'youtube' => 'https://youtube.com/@club',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('message', __('messages.site_social_links_updated'))
        ->assertJsonPath('data.social_links.instagram', 'https://instagram.com/club')
        ->assertJsonPath('data.social_links.youtube', 'https://youtube.com/@club')
        ->assertJsonMissingPath('data.social_links.facebook');
});

test('organisation can show social links for a site', function () {
    [, , $site, $token] = siteSocialLinksFixture();

    $site->update([
        'social_links' => [
            'facebook' => 'https://facebook.com/club',
        ],
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/site/social-links?site_id='.$site->id)
        ->assertOk()
        ->assertJsonPath('message', __('messages.site_social_links_show'))
        ->assertJsonPath('data.social_links.facebook', 'https://facebook.com/club');
});
