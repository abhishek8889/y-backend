<?php

namespace App\Enum;

enum PermissionEnum: string
{
    case OrganisationsView = 'organisations.view';
    case OrganisationsUpdate = 'organisations.update';
    case OrganisationsSuspend = 'organisations.suspend';
    case OrganisationsDelete = 'organisations.delete';
    case UsersView = 'users.view';
    case UsersUpdate = 'users.update';
    case UsersSuspend = 'users.suspend';
    case PlatformStaffView = 'platform_staff.view';
    case PlatformStaffCreate = 'platform_staff.create';
    case PlatformStaffUpdate = 'platform_staff.update';
    case PlatformStaffDelete = 'platform_staff.delete';
    case PlatformRolesView = 'platform_roles.view';
    case PlatformRolesCreate = 'platform_roles.create';
    case PlatformRolesUpdate = 'platform_roles.update';
    case PlatformRolesDelete = 'platform_roles.delete';
    case PayoutsView = 'payouts.view';
    case PayoutsReview = 'payouts.review';
    case PayoutsProcess = 'payouts.process';
    case PlatformReportsView = 'platform.reports.view';
    case PlatformSettingsUpdate = 'platform.settings.update';

    case OrganisationUpdate = 'organisation.update';
    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsPublish = 'events.publish';
    case TicketsView = 'tickets.view';
    case TicketsCreate = 'tickets.create';
    case TicketsUpdate = 'tickets.update';
    case TicketsDelete = 'tickets.delete';
    case TicketsRefund = 'tickets.refund';
    case OrdersView = 'orders.view';
    case OrdersUpdate = 'orders.update';
    case AttendeesView = 'attendees.view';
    case AttendeesExport = 'attendees.export';
    case StaffView = 'staff.view';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case StaffDelete = 'staff.delete';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case ReportsView = 'reports.view';

    public function scope(): PermissionScopeEnum
    {
        return match ($this) {
            self::OrganisationsView,
            self::OrganisationsUpdate,
            self::OrganisationsSuspend,
            self::OrganisationsDelete,
            self::UsersView,
            self::UsersUpdate,
            self::UsersSuspend,
            self::PlatformStaffView,
            self::PlatformStaffCreate,
            self::PlatformStaffUpdate,
            self::PlatformStaffDelete,
            self::PlatformRolesView,
            self::PlatformRolesCreate,
            self::PlatformRolesUpdate,
            self::PlatformRolesDelete,
            self::PayoutsView,
            self::PayoutsReview,
            self::PayoutsProcess,
            self::PlatformReportsView,
            self::PlatformSettingsUpdate => PermissionScopeEnum::PLATFORM,
            default => PermissionScopeEnum::ORGANISATION,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrganisationsView => 'View organisations on the platform',
            self::OrganisationsUpdate => 'Update organisation details',
            self::OrganisationsSuspend => 'Suspend organisations',
            self::OrganisationsDelete => 'Delete organisations',
            self::UsersView => 'View platform users',
            self::UsersUpdate => 'Update platform users',
            self::UsersSuspend => 'Suspend platform users',
            self::PlatformStaffView => 'View platform staff',
            self::PlatformStaffCreate => 'Add platform staff',
            self::PlatformStaffUpdate => 'Update platform staff',
            self::PlatformStaffDelete => 'Remove platform staff',
            self::PlatformRolesView => 'View platform roles',
            self::PlatformRolesCreate => 'Create platform roles',
            self::PlatformRolesUpdate => 'Update platform roles',
            self::PlatformRolesDelete => 'Delete platform roles',
            self::PayoutsView => 'View payouts',
            self::PayoutsReview => 'Review payouts',
            self::PayoutsProcess => 'Process payouts',
            self::PlatformReportsView => 'View platform reports',
            self::PlatformSettingsUpdate => 'Update platform settings',
            self::OrganisationUpdate => 'Update organisation profile',
            self::EventsView => 'View events',
            self::EventsCreate => 'Create events',
            self::EventsUpdate => 'Update events',
            self::EventsDelete => 'Delete events',
            self::EventsPublish => 'Publish events',
            self::TicketsView => 'View tickets',
            self::TicketsCreate => 'Create tickets',
            self::TicketsUpdate => 'Update tickets',
            self::TicketsDelete => 'Delete tickets',
            self::TicketsRefund => 'Refund tickets',
            self::OrdersView => 'View orders',
            self::OrdersUpdate => 'Update orders',
            self::AttendeesView => 'View attendees',
            self::AttendeesExport => 'Export attendees',
            self::StaffView => 'View organisation staff',
            self::StaffCreate => 'Add organisation staff',
            self::StaffUpdate => 'Update organisation staff',
            self::StaffDelete => 'Remove organisation staff',
            self::RolesView => 'View organisation roles',
            self::RolesCreate => 'Create organisation roles',
            self::RolesUpdate => 'Update organisation roles',
            self::RolesDelete => 'Delete organisation roles',
            self::ReportsView => 'View organisation reports',
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
}
