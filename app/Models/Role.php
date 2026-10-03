<?php

namespace App\Models;

use App\Enum\PermissionScopeEnum;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organisation_id', 'name', 'slug', 'description'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Build a URL-safe slug from a role name.
     */
    public static function slugFromName(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'role';
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * @return BelongsToMany<OrganiserStaff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(OrganiserStaff::class, 'staff_roles');
    }

    public function grantPermission(Permission $permission): void
    {
        if ($permission->scope !== PermissionScopeEnum::ORGANISATION) {
            throw new InvalidArgumentException('Organisation roles may only receive organisation permissions.');
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
