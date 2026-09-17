<?php

namespace App\Models;

use App\Enum\StatusEnum;
use Database\Factories\OrganiserStaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property StatusEnum $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('organiser_staff')]
#[Fillable(['organisation_id', 'user_id', 'status'])]
class OrganiserStaff extends Model
{
    /** @use HasFactory<OrganiserStaffFactory> */
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
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'staff_roles');
    }

    public function assignRole(Role $role): void
    {
        if ($role->organisation_id !== $this->organisation_id) {
            throw new InvalidArgumentException('Staff may only receive roles from their organisation.');
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }
}
