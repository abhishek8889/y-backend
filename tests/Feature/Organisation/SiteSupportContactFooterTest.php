<?php

use App\Enum\SiteStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Site;
use App\Models\SiteSupportContact;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Site, 2: string}
 */
function siteSupportContactFooterFixture(): array
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

test('support contact create defaults show_in_footer to false', function () {
    [, $site, $token] = siteSupportContactFooterFixture();

    test()->withToken($token)
        ->postJson('/api/organisation/site/support-contact/create', [
            'site_id' => $site->id,
            'department_name' => 'Cleaning',
            'email' => 'clean@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.contact.show_in_footer', false);
});

test('only one support contact per site can show in footer', function () {
    [, $site, $token] = siteSupportContactFooterFixture();

    $first = SiteSupportContact::query()->create([
        'site_id' => $site->id,
        'department_name' => 'First',
        'email' => 'first@example.com',
        'status' => StatusEnum::ACTIVE,
        'show_in_footer' => true,
    ]);

    $secondResponse = test()->withToken($token)
        ->postJson('/api/organisation/site/support-contact/create', [
            'site_id' => $site->id,
            'department_name' => 'Second',
            'email' => 'second@example.com',
            'show_in_footer' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.contact.show_in_footer', true);

    $secondId = (int) $secondResponse->json('data.contact.id');

    expect($first->fresh()->show_in_footer)->toBeFalse()
        ->and(SiteSupportContact::query()->find($secondId)?->show_in_footer)->toBeTrue();

    test()->withToken($token)
        ->postJson('/api/organisation/site/support-contact/update/'.$first->id, [
            'show_in_footer' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.contact.show_in_footer', true);

    expect($first->fresh()->show_in_footer)->toBeTrue()
        ->and(SiteSupportContact::query()->find($secondId)?->show_in_footer)->toBeFalse();
});
