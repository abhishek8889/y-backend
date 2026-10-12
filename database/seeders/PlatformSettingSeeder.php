<?php

namespace Database\Seeders;

use App\Enum\PlatformSettingKeyEnum;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    /**
     * Seed platform settings.
     *
     * Add new settings to this array (key, value, description).
     * Initial values can come from env; afterwards they are managed in DB.
     */
    public function run(): void
    {
        PlatformSetting::query()
            ->whereIn('key', [
                'platform_fees',
                'booking_platform_fees',
            ])
            ->delete();

        $settings = [
            [
                'key' => PlatformSettingKeyEnum::BOOKING_PLATFORM_FEES_PERCENTAGE->value,
                'value' => (int) env('BOOKING_PLATFORM_FEES_PERCENTAGE', 0),
                'description' => 'Platform fee percentage applied to each booking line total (e.g. 5 = 5%).',
            ],
            [
                'key' => PlatformSettingKeyEnum::BOOKING_PAYOUT_HOLD_DAYS->value,
                'value' => (int) env('BOOKING_PAYOUT_HOLD_DAYS', 10),
                'description' => 'Days to keep organiser earnings reserved after an event is completed before funds become available for payout.',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                ],
            );
        }
    }
}
