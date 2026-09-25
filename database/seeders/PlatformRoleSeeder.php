<?php

namespace Database\Seeders;

use App\Enum\PlatformRoleEnum;
use App\Models\PlatformRole;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    /**
     * Seed the default platform roles.
     */
    public function run(): void
    {
        foreach (PlatformRoleEnum::cases() as $role) {
            PlatformRole::query()->firstOrCreate([
                'name' => $role->value,
            ]);
        }
    }
}
