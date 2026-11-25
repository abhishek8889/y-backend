<?php

use App\Enum\SiteStatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use Illuminate\Support\Str;

test('organisation get-microsite-list returns id name hostname for auth org only', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $site = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $organisation->id,
        'name' => 'Alpha Club',
        'status' => SiteStatusEnum::DRAFT,
        'created_by' => $user->id,
    ]);

    SiteDomain::query()->create([
        'site_id' => $site->id,
        'hostname' => 'alpha.example.com',
    ]);

    $otherOrg = Organisation::factory()->create(['approve_status' => true]);
    $otherSite = Site::query()->create([
        'unique_id' => (string) Str::ulid(),
        'organisation_id' => $otherOrg->id,
        'name' => 'Other Club',
        'status' => SiteStatusEnum::DRAFT,
    ]);
    SiteDomain::query()->create([
        'site_id' => $otherSite->id,
        'hostname' => 'other.example.com',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->getJson('/api/organisation/site/get-microsite-list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.microsite_list'))
        ->assertJsonPath('data.sites.0.id', $site->id)
        ->assertJsonPath('data.sites.0.name', 'Alpha Club')
        ->assertJsonPath('data.sites.0.hostname', 'alpha.example.com');

    $ids = collect($response->json('data.sites'))->pluck('id');
    expect($ids)->toContain($site->id)->not->toContain($otherSite->id);
});
