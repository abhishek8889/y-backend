<?php

namespace App\Models;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
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

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone
 * @property string $password
 * @property StatusEnum $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone', 'status'])]
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
