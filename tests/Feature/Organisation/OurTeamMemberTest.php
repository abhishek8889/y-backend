<?php

use App\Enum\SiteStatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\OurTeamMember;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Site, 2: string}
 */
function ourTeamMemberFixture(): array
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

test('organisation can create list update and delete team members', function () {
    [$user, $site, $token] = ourTeamMemberFixture();

    $create = test()->withToken($token)
        ->postJson('/api/organisation/site/our-team-member/create', [
            'site_id' => $site->id,
            'user_id' => $user->id,
            'name' => 'Alex Coach',
            'designation' => 'Head Coach',
            'about' => 'Leads the senior team.',
            'joined_at' => '2024-01-15',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.our_team_member_created'))
        ->assertJsonPath('data.member.name', 'Alex Coach')
        ->assertJsonPath('data.member.designation', 'Head Coach')
        ->assertJsonPath('data.member.user_id', $user->id)
        ->assertJsonPath('data.member.joined_at', '2024-01-15T00:00:00+00:00');

    $memberId = (int) $create->json('data.member.id');
    $memberUniqueId = (string) $create->json('data.member.member_unique_id');

    expect($memberUniqueId)->toStartWith('OTM')->toHaveLength(7);

    test()->withToken($token)
        ->getJson('/api/organisation/site/our-team-member/list?site_id='.$site->id)
        ->assertOk()
        ->assertJsonPath('message', __('messages.our_team_member_list'))
        ->assertJsonPath('data.items.0.id', $memberId)
        ->assertJsonPath('data.items.0.name', 'Alex Coach');

    test()->withToken($token)
        ->postJson('/api/organisation/site/our-team-member/update/'.$memberId, [
            'name' => 'Alex Updated',
            'designation' => 'Director of Football',
        ])
        ->assertOk()
        ->assertJsonPath('message', __('messages.our_team_member_updated'))
        ->assertJsonPath('data.member.name', 'Alex Updated')
        ->assertJsonPath('data.member.designation', 'Director of Football');

    test()->withToken($token)
        ->deleteJson('/api/organisation/site/our-team-member/delete/'.$memberId)
        ->assertOk()
        ->assertJsonPath('message', __('messages.our_team_member_deleted'))
        ->assertJsonPath('data.deleted', true);

    expect(OurTeamMember::query()->find($memberId))->toBeNull();
});

test('team member create requires name and site_id', function () {
    [, , $token] = ourTeamMemberFixture();

    test()->withToken($token)
        ->postJson('/api/organisation/site/our-team-member/create', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});
