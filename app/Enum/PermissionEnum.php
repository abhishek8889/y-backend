<?php

namespace App\Enum;

enum PermissionEnum: string
{
    case OrganisationsRead = 'organisations.read';
    case OrganisationsCreate = 'organisations.create';
    case OrganisationsUpdate = 'organisations.update';
    case OrganisationsDelete = 'organisations.delete';
    case OrganisationsManageApproveStatus = 'organisations.manage_approve_status';
    case PlatformSettingsUpdate = 'settings.update';
    case VenuesRead = 'venues.read';
    case VenuesCreate = 'venues.create';
    case VenuesUpdate = 'venues.update';
    case VenuesDelete = 'venues.delete';
    case EventsRead = 'events.read';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';

    public function module(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    public function scope(): PermissionScopeEnum
    {
        return match ($this) {
            self::OrganisationsRead,
            self::OrganisationsCreate,
            self::OrganisationsUpdate,
            self::OrganisationsDelete,
            self::OrganisationsManageApproveStatus,
            self::PlatformSettingsUpdate => PermissionScopeEnum::PLATFORM,

            self::VenuesRead,
            self::VenuesCreate,
            self::VenuesUpdate,
            self::VenuesDelete,
            self::EventsRead,
            self::EventsCreate,
            self::EventsUpdate,
            self::EventsDelete => PermissionScopeEnum::ORGANISATION,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrganisationsRead => 'Read organisation',
            self::OrganisationsCreate => 'Create organisation',
            self::OrganisationsUpdate => 'Update organisation',
            self::OrganisationsDelete => 'Delete organisation',
            self::OrganisationsManageApproveStatus => 'Manage organisation approve status',
            self::PlatformSettingsUpdate => 'Update platform settings',
            self::VenuesRead => 'Read venue',
            self::VenuesCreate => 'Create venue',
            self::VenuesUpdate => 'Update venue',
            self::VenuesDelete => 'Delete venue',
            self::EventsRead => 'Read event',
            self::EventsCreate => 'Create event',
            self::EventsUpdate => 'Update event',
            self::EventsDelete => 'Delete event',
        };
    }

    /**
     * @return list<self>
     */
    public static function forScope(PermissionScopeEnum $scope): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => $permission->scope() === $scope,
        ));
    }

    /**
     * @return list<self>
     */
    public static function forModule(string $module): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => $permission->module() === $module,
        ));
    }
}
