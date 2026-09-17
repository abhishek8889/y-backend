<?php

namespace App\Models;

use App\Enum\StatusEnum;
use Database\Factories\PlatformStaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property StatusEnum $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('platform_staff')]
#[Fillable(['user_id', 'status'])]
class PlatformStaff extends Model
{
    /** @use HasFactory<PlatformStaffFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<PlatformRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_staff_roles');
    }

    public function assignRole(PlatformRole $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
    }
}
