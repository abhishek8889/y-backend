<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
use App\Models\Organisation;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed default organisation roles (e.g. Owner) for every organisation.
     */
    public function run(): void
    {
        Organisation::query()->each(function (Organisation $organisation): void {
            foreach (RoleEnum::cases() as $role) {
                Role::query()->firstOrCreate([
                    'organisation_id' => $organisation->id,
                    'name' => $role->value,
                ]);
            }
        });
    }
}
