<?php

namespace App\Models;

use App\Enum\PermissionScopeEnum;
use Database\Factories\PlatformRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class PlatformRole extends Model
{
    /** @use HasFactory<PlatformRoleFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'platform_role_permissions');
    }

    /**
     * @return BelongsToMany<PlatformStaff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(PlatformStaff::class, 'platform_staff_roles');
    }

    public function grantPermission(Permission $permission): void
    {
        if ($permission->scope !== PermissionScopeEnum::PLATFORM) {
            throw new InvalidArgumentException('Platform roles may only receive platform permissions.');
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
