<?php

namespace Database\Seeders;

use App\Enum\PermissionEnum;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's permission catalog.
     */
    public function run(): void
    {
        $names = [];

        foreach (PermissionEnum::cases() as $permission) {
            $names[] = $permission->value;

            Permission::query()->updateOrCreate(
                ['name' => $permission->value],
                [
                    'scope' => $permission->scope(),
                    'description' => $permission->description(),
                ],
            );
        }

        Permission::query()->whereNotIn('name', $names)->delete();
    }
}
