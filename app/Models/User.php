<?php

namespace App\Models;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\RoleEnum;
use App\Enum\StatusEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $country
 * @property string $password
 * @property string|null $profile_image
 * @property StatusEnum $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'first_name',
    'last_name',
    'email',
    'password',
    'phone',
    'country_code',
    'country',
    'profile_image',
    'status',
    'email_verified_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => StatusEnum::class,
        ];
    }

    /**
     * @return HasMany<Organisation, $this>
     */
    public function ownedOrganisations(): HasMany
    {
        return $this->hasMany(Organisation::class, 'owner_id');
    }

    /**
     * @return HasMany<OrganiserStaff, $this>
     */
    public function organiserStaff(): HasMany
    {
        return $this->hasMany(OrganiserStaff::class);
    }

    /**
     * @return HasOne<PlatformStaff, $this>
     */
    public function platformStaff(): HasOne
    {
        return $this->hasOne(PlatformStaff::class);
    }

    /**
     * @return array{scope: string|null, roles: list<string>, organisation_id: int|null}
     */
    public function loginContext(): array
    {
        $this->loadMissing([
            'platformStaff.roles',
            'organiserStaff.roles',
            'ownedOrganisations',
        ]);

        $platformStaff = $this->platformStaff;

        if ($platformStaff !== null && $platformStaff->status === StatusEnum::ACTIVE) {
            return [
                'scope' => PermissionScopeEnum::PLATFORM->value,
                'roles' => $platformStaff->roles->pluck('name')->values()->all(),
                'organisation_id' => null,
            ];
        }

        $staff = $this->organiserStaff
            ->filter(fn (OrganiserStaff $membership): bool => $membership->status === StatusEnum::ACTIVE)
            ->sortBy('id')
            ->first();

        if ($staff !== null) {
            return [
                'scope' => PermissionScopeEnum::ORGANISATION->value,
                'roles' => $staff->roles
                    ->map(fn (Role $role): string => $role->slug ?: $role->name)
                    ->values()
                    ->all(),
                'organisation_id' => $staff->organisation_id,
            ];
        }

        $organisation = $this->ownedOrganisations->sortBy('id')->first();

        if ($organisation !== null) {
            return [
                'scope' => PermissionScopeEnum::ORGANISATION->value,
                'roles' => [],
                'organisation_id' => $organisation->id,
            ];
        }

        return [
            'scope' => null,
            'roles' => [],
            'organisation_id' => null,
        ];
    }

    /**
     * Organisation-only login context (ignores platform staff membership).
     *
     * @return array{scope: string, roles: list<string>, organisation_id: int}|null
     */
    public function organisationLoginContext(): ?array
    {
        $this->loadMissing([
            'organiserStaff.roles',
            'ownedOrganisations',
        ]);

        $staff = $this->organiserStaff
            ->filter(fn (OrganiserStaff $membership): bool => $membership->status === StatusEnum::ACTIVE)
            ->sortBy('id')
            ->first();

        if ($staff !== null) {
            return [
                'scope' => PermissionScopeEnum::ORGANISATION->value,
                'roles' => $staff->roles
                    ->map(fn (Role $role): string => $role->slug ?: $role->name)
                    ->values()
                    ->all(),
                'organisation_id' => $staff->organisation_id,
            ];
        }

        $organisation = $this->ownedOrganisations->sortBy('id')->first();

        if ($organisation !== null) {
            return [
                'scope' => PermissionScopeEnum::ORGANISATION->value,
                'roles' => [],
                'organisation_id' => $organisation->id,
            ];
        }

        return null;
    }

    /**
     * Permission names for the current login scope, grouped by module.
     *
     * @param  array{scope: string|null, roles: list<string>, organisation_id: int|null}  $context
     * @return array<string, list<string>>
     */
    public function permissionsGroupedByModule(array $context): array
    {
        $names = match ($context['scope'] ?? null) {
            PermissionScopeEnum::PLATFORM->value => $this->resolvePlatformPermissionNames($context['roles'] ?? []),
            PermissionScopeEnum::ORGANISATION->value => $this->resolveOrganisationPermissionNames(
                $context['roles'] ?? [],
                $context['organisation_id'] ?? null,
            ),
            default => collect(),
        };

        return $names
            ->unique()
            ->sort()
            ->groupBy(fn (string $name): string => explode('.', $name, 2)[0])
            ->map(fn (Collection $group): array => $group->values()->all())
            ->all();
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, string>
     */
    private function resolvePlatformPermissionNames(array $roles): Collection
    {
        if (in_array(PlatformRoleEnum::SUPER_ADMIN->value, $roles, true)) {
            return Permission::query()
                ->platform()
                ->orderBy('name')
                ->pluck('name');
        }

        $this->loadMissing('platformStaff.roles.permissions');

        return $this->platformStaff
            ?->roles
            ->flatMap(fn (PlatformRole $role): Collection => $role->permissions->pluck('name'))
            ->values() ?? collect();
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, string>
     */
    private function resolveOrganisationPermissionNames(array $roles, ?int $organisationId): Collection
    {
        $isOwner = in_array(RoleEnum::OWNER->value, $roles, true)
            || (
                $organisationId !== null
                && $this->ownedOrganisations->contains(
                    fn (Organisation $organisation): bool => $organisation->id === $organisationId,
                )
            );

        if ($isOwner) {
            return Permission::query()
                ->organisation()
                ->orderBy('name')
                ->pluck('name');
        }

        $this->loadMissing('organiserStaff.roles.permissions');

        return $this->organiserStaff
            ->filter(
                fn (OrganiserStaff $membership): bool => $membership->status === StatusEnum::ACTIVE
                    && ($organisationId === null || $membership->organisation_id === $organisationId),
            )
            ->flatMap(
                fn (OrganiserStaff $membership): Collection => $membership->roles
                    ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name')),
            )
            ->values();
    }

    public function owns(Organisation $organisation): bool
    {
        return $organisation->owner_id === $this->id;
    }

    public function hasOrganisationPermission(Organisation $organisation, PermissionEnum|string $permission): bool
    {
        $permissionName = $this->permissionName($permission);

        if ($this->status !== StatusEnum::ACTIVE) {
            return false;
        }

        if ($permission instanceof PermissionEnum && $permission->scope() !== PermissionScopeEnum::ORGANISATION) {
            return false;
        }

        if ($this->owns($organisation)) {
            return true;
        }

        return $this->organiserStaff()
            ->whereBelongsTo($organisation)
            ->where('status', StatusEnum::ACTIVE)
            ->whereHas(
                'roles.permissions',
                fn (Builder $query) => $query->where('name', $permissionName),
            )
            ->exists();
    }

    public function hasPlatformPermission(PermissionEnum|string $permission): bool
    {
        $permissionName = $this->permissionName($permission);

        if ($this->status !== StatusEnum::ACTIVE) {
            return false;
        }

        if ($permission instanceof PermissionEnum && $permission->scope() !== PermissionScopeEnum::PLATFORM) {
            return false;
        }

        return $this->platformStaff()
            ->where('status', StatusEnum::ACTIVE)
            ->whereHas(
                'roles.permissions',
                fn (Builder $query) => $query->where('name', $permissionName),
            )
            ->exists();
    }

    private function permissionName(PermissionEnum|string $permission): string
    {
        return $permission instanceof PermissionEnum ? $permission->value : $permission;
    }
}
