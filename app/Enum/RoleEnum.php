<?php

namespace App\Enum;

enum RoleEnum: string
{
    /** Owner of the organisation. */
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
        };
    }

    /**
     * Default permissions granted whenever this role is provisioned.
     *
     * @return list<PermissionEnum>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::OWNER => PermissionEnum::forScope(PermissionScopeEnum::ORGANISATION),
        };
    }
}
