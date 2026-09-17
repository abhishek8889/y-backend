<?php

namespace App\Models;

use App\Enum\PermissionScopeEnum;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property PermissionScopeEnum $scope
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'scope', 'description'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => PermissionScopeEnum::class,
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * @return BelongsToMany<PlatformRole, $this>
     */
    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_permissions');
    }

    #[Scope]
    protected function platform(Builder $query): Builder
    {
        return $query->where('scope', PermissionScopeEnum::PLATFORM);
    }

    #[Scope]
    protected function organisation(Builder $query): Builder
    {
        return $query->where('scope', PermissionScopeEnum::ORGANISATION);
    }
}
