<?php

namespace App\Models;

use App\Enum\PlatformSettingKeyEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property string|null $description
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'key',
    'value',
    'description',
    'updated_by',
])]
class PlatformSetting extends Model
{
    /**
     * JSON scalar or structured value (e.g. 5, "text", [...], {...}).
     *
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): mixed {
                if ($value === null) {
                    return null;
                }

                if (! is_string($value)) {
                    return $value;
                }

                return json_decode($value, true);
            },
            set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function findByKey(PlatformSettingKeyEnum|string $key): ?self
    {
        $keyValue = $key instanceof PlatformSettingKeyEnum ? $key->value : $key;

        return static::query()->where('key', $keyValue)->first();
    }

    public static function getValue(PlatformSettingKeyEnum|string $key, mixed $default = null): mixed
    {
        $setting = static::findByKey($key);

        if ($setting === null) {
            return $default;
        }

        return $setting->value;
    }
}
