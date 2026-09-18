<?php

use App\Enum\PermissionEnum;
use App\Enum\StatusEnum;
use App\Models\OrganiserStaff;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('creates an active platform super admin user', function () {
    $this->seed([
        PermissionSeeder::class,
        PlatformRoleSeeder::class,
        UserSeeder::class,
    ]);

    $user = User::query()->where('email', UserSeeder::EMAIL)->first();

    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Super');
    expect($user->last_name)->toBe('Admin');
    expect($user->status)->toBe(StatusEnum::ACTIVE);
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::isHashed($user->password))->toBeTrue();
    expect($user->platformStaff)->not->toBeNull();
    expect($user->platformStaff->status)->toBe(StatusEnum::ACTIVE);
    expect($user->platformStaff->roles->pluck('name')->all())->toContain('Super Admin');
    expect($user->hasPlatformPermission(PermissionEnum::OrganisationsRead))->toBeTrue();
    expect(OrganiserStaff::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('does not create a second super admin on reseed', function () {
    $this->seed([
        PermissionSeeder::class,
        PlatformRoleSeeder::class,
        UserSeeder::class,
        UserSeeder::class,
    ]);

    expect(User::query()->where('email', UserSeeder::EMAIL)->count())->toBe(1);
});
