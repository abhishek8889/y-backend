<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;

test('unapproved organisations cannot access organisation routes', function () {
    [$user] = organisationOwnerWithToken(approveStatus: false);

    $this->withToken(organisationApprovedToken($user))
        ->getJson('/api/organisation/venue/list')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.organisation_not_approved'))
        ->assertJsonPath('error', __('messages.organisation_not_approved'));
});

test('unapproved organisations still include the rejection reason when present', function () {
    [$user] = organisationOwnerWithToken(
        approveStatus: false,
        approveStatusReason: 'Incomplete organisation profile.',
    );

    $this->withToken(organisationApprovedToken($user))
        ->getJson('/api/organisation/venue/list')
        ->assertForbidden()
        ->assertJsonPath('message', __('messages.organisation_not_approved'))
        ->assertJsonPath('error', 'Incomplete organisation profile.');
});

test('approved organisations can pass the organisation approved middleware', function () {
    [$user] = organisationOwnerWithToken(approveStatus: true);

    $this->withToken(organisationApprovedToken($user))
        ->getJson('/api/organisation/venue/list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_list'));
});

test('organisation details remain available when the organisation is not approved', function () {
    [$user] = organisationOwnerWithToken(approveStatus: false);

    $this->withToken(organisationApprovedToken($user))
        ->getJson('/api/my-org/details')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.approve_status', false);
});

/**
 * @return array{0: User, 1: Organisation}
 */
function organisationOwnerWithToken(
    bool $approveStatus,
    ?string $approveStatusReason = null,
): array {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => $approveStatus,
        'approve_status_reason' => $approveStatusReason,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    return [$user, $organisation];
}

function organisationApprovedToken(User $user): string
{
    return test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');
}
