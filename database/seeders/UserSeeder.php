<?php

namespace Database\Seeders;

use App\Enum\StatusEnum;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const string EMAIL = 'admin@example.com';

    /**
     * Pre-hashed password. Do not store the plain-text value in this file.
     */
    private const string PASSWORD_HASH = '$2y$12$bG.uoFgvX/5gsTCpX6K9GeshTQYPQ2mOB2ht1Zv4KIoWtWYiodSJe';

    /**
     * Seed the platform Super Admin user.
     */
    public function run(): void
    {
        $now = now();

        $values = [
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'password' => self::PASSWORD_HASH,
            'status' => StatusEnum::ACTIVE->value,
            'email_verified_at' => $now,
            'updated_at' => $now,
        ];

        if (User::query()->where('email', self::EMAIL)->exists()) {
            User::query()->where('email', self::EMAIL)->update($values);
        } else {
            User::query()->insert([
                ...$values,
                'email' => self::EMAIL,
                'created_at' => $now,
            ]);
        }

        $user = User::query()->where('email', self::EMAIL)->firstOrFail();

        $staff = PlatformStaff::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => StatusEnum::ACTIVE],
        );

        $role = PlatformRole::query()->where('name', 'Super Admin')->firstOrFail();

        $staff->assignRole($role);
    }
}
