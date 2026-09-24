<?php

use App\Enum\MailSenderEnum;
use App\Enum\PermissionEnum;
use App\Enum\PlatformRoleEnum;
use App\Mail\AppMail;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('platform users with organisations.manage_approve_status can approve an organisation', function () {
    Mail::fake();

    $user = platformUserWithManageApproveStatus();
    $owner = User::factory()->create(['email' => 'owner@example.com', 'first_name' => 'Ada']);
    $organisation = Organisation::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Ada Events',
        'approve_status' => false,
    ]);

    $this->withToken(platformApproveStatusToken($user))
        ->postJson('/api/platform/organisation/manage-approve-status', [
            'organisation_id' => $organisation->id,
            'approve_status' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.platform_organisation_approve_status_updated'))
        ->assertJsonPath('data.id', $organisation->id)
        ->assertJsonPath('data.approve_status', true)
        ->assertJsonPath('data.approve_status_reason', null);

    $this->assertDatabaseHas('organisations', [
        'id' => $organisation->id,
        'approve_status' => true,
        'approve_status_reason' => null,
    ]);

    Mail::assertSent(AppMail::class, function (AppMail $mail) use ($owner): bool {
        return $mail->hasTo($owner->email)
            && $mail->mailView === 'mail.organisation-approved'
            && $mail->sentBy === MailSenderEnum::PLATFORM;
    });
});

test('platform users with organisations.manage_approve_status can reject an organisation with a reason', function () {
    Mail::fake();

    $user = platformUserWithManageApproveStatus();
    $owner = User::factory()->create(['email' => 'owner@example.com', 'first_name' => 'Ada']);
    $organisation = Organisation::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Ada Events',
        'approve_status' => true,
    ]);

    $this->withToken(platformApproveStatusToken($user))
        ->postJson('/api/platform/organisation/manage-approve-status', [
            'organisation_id' => $organisation->id,
            'approve_status' => false,
            'approve_status_reason' => 'Incomplete organisation profile.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.approve_status', false)
        ->assertJsonPath('data.approve_status_reason', 'Incomplete organisation profile.');

    $this->assertDatabaseHas('organisations', [
        'id' => $organisation->id,
        'approve_status' => false,
        'approve_status_reason' => 'Incomplete organisation profile.',
    ]);

    Mail::assertSent(AppMail::class, function (AppMail $mail) use ($owner): bool {
        return $mail->hasTo($owner->email)
            && $mail->mailView === 'mail.organisation-rejected'
            && $mail->data['reason'] === 'Incomplete organisation profile.'
            && $mail->sentBy === MailSenderEnum::PLATFORM;
    });
});

test('rejecting an organisation requires a reason', function () {
    $user = platformUserWithManageApproveStatus();
    $organisation = Organisation::factory()->create(['approve_status' => true]);

    $this->withToken(platformApproveStatusToken($user))
        ->postJson('/api/platform/organisation/manage-approve-status', [
            'organisation_id' => $organisation->id,
            'approve_status' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.required', [
            'attribute' => 'rejection reason',
        ]));
});

test('manage approve status returns validation error when organisation is missing', function () {
    $user = platformUserWithManageApproveStatus();

    $this->withToken(platformApproveStatusToken($user))
        ->postJson('/api/platform/organisation/manage-approve-status', [
            'organisation_id' => 999999,
            'approve_status' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.organisation_not_found'))
        ->assertJsonPath('error', __('messages.organisation_not_found'));
});

test('platform users without organisations.manage_approve_status cannot manage approve status', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => 'support']);
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsUpdate)->create();
    $role->grantPermission($permission);
    $staff->assignRole($role);

    $organisation = Organisation::factory()->create();

    $this->withToken(platformApproveStatusToken($user))
        ->postJson('/api/platform/organisation/manage-approve-status', [
            'organisation_id' => $organisation->id,
            'approve_status' => true,
        ])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.permission_denied'));
});

test('manage approve status returns 401 when the access token is missing', function () {
    $this->postJson('/api/platform/organisation/manage-approve-status', [
        'organisation_id' => 1,
        'approve_status' => true,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'));
});

function platformUserWithManageApproveStatus(): User
{
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsManageApproveStatus)->create();
    $role->grantPermission($permission);
    $staff->assignRole($role);

    return $user;
}

function platformApproveStatusToken(User $user): string
{
    return test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');
}
