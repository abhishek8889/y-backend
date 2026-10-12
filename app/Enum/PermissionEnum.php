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

    // Organisation dashboard
    case VenuesRead = 'venues.read';
    case VenuesCreate = 'venues.create';
    case VenuesUpdate = 'venues.update';
    case VenuesDelete = 'venues.delete';
    case EventsRead = 'events.read';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';

    case CustomersRead = 'org_customers.read';
    case CustomersCreate = 'org_customers.create';
    case CustomersUpdate = 'org_customers.update';
    case CustomersDelete = 'org_customers.delete';

    case ClubsitesRead = 'org_clubsites.read';
    case ClubsitesCreate = 'org_clubsites.create';
    case ClubsitesUpdate = 'org_clubsites.update';
    case ClubsitesDelete = 'org_clubsites.delete';

    case WhitelabelRead = 'org_whitelabel.read';
    case WhitelabelCreate = 'org_whitelabel.create';
    case WhitelabelUpdate = 'org_whitelabel.update';
    case WhitelabelDelete = 'org_whitelabel.delete';

    case RolesRead = 'org_roles.read';
    case RolesCreate = 'org_roles.create';
    case RolesUpdate = 'org_roles.update';
    case RolesDelete = 'org_roles.delete';

    case StaffMembersRead = 'org_staff_members.read';
    case StaffMembersCreate = 'org_staff_members.create';
    case StaffMembersUpdate = 'org_staff_members.update';
    case StaffMembersDelete = 'org_staff_members.delete';

    case FinanceRead = 'org_finance.read';
    case FinanceCreate = 'org_finance.create';
    case FinanceUpdate = 'org_finance.update';
    case FinanceDelete = 'org_finance.delete';

    case OrganisationSettingsRead = 'org_settings.read';

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
            self::EventsDelete,
            self::CustomersRead,
            self::CustomersCreate,
            self::CustomersUpdate,
            self::CustomersDelete,
            self::ClubsitesRead,
            self::ClubsitesCreate,
            self::ClubsitesUpdate,
            self::ClubsitesDelete,
            self::WhitelabelRead,
            self::WhitelabelCreate,
            self::WhitelabelUpdate,
            self::WhitelabelDelete,
            self::RolesRead,
            self::RolesCreate,
            self::RolesUpdate,
            self::RolesDelete,
            self::StaffMembersRead,
            self::StaffMembersCreate,
            self::StaffMembersUpdate,
            self::StaffMembersDelete,
            self::FinanceRead,
            self::FinanceCreate,
            self::FinanceUpdate,
            self::FinanceDelete,
            self::OrganisationSettingsRead => PermissionScopeEnum::ORGANISATION,
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

            self::CustomersRead => 'Read organisation customers',
            self::CustomersCreate => 'Create organisation customers',
            self::CustomersUpdate => 'Update organisation customers',
            self::CustomersDelete => 'Delete organisation customers',

            self::ClubsitesRead => 'Read organisation clubsites',
            self::ClubsitesCreate => 'Create organisation clubsites',
            self::ClubsitesUpdate => 'Update organisation clubsites',
            self::ClubsitesDelete => 'Delete organisation clubsites',

            self::WhitelabelRead => 'Read organisation whitelabel',
            self::WhitelabelCreate => 'Create organisation whitelabel',
            self::WhitelabelUpdate => 'Update organisation whitelabel',
            self::WhitelabelDelete => 'Delete organisation whitelabel',

            self::RolesRead => 'Read organisation roles',
            self::RolesCreate => 'Create organisation roles',
            self::RolesUpdate => 'Update organisation roles',
            self::RolesDelete => 'Delete organisation roles',

            self::StaffMembersRead => 'Read organisation staff members',
            self::StaffMembersCreate => 'Create organisation staff members',
            self::StaffMembersUpdate => 'Update organisation staff members',
            self::StaffMembersDelete => 'Delete organisation staff members',

            self::FinanceRead => 'Read organisation finance',
            self::FinanceCreate => 'Create organisation finance',
            self::FinanceUpdate => 'Update organisation finance',
            self::FinanceDelete => 'Delete organisation finance',

            self::OrganisationSettingsRead => 'Read organisation settings',
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
