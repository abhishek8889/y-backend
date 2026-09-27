<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Services\Organisation\OrganisationRoleService;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed default organisation roles (e.g. Owner) for every organisation.
     */
    public function run(): void
    {
        $roles = app(OrganisationRoleService::class);

        Organisation::query()->each(function (Organisation $organisation) use ($roles): void {
            $roles->provisionDefaultRoles($organisation);
        });
    }
}
