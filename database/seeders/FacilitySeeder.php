<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    /**
     * Seed system facilities (organisation_id = null).
     */
    public function run(): void
    {
        $facilities = [
            'WiFi',
            'Toilets',
            'Audio / AV',
            'Parking',
            'Bar',
            'Changing Rooms',
            'Accessible Entrance',
            'Catering',
            'Outdoor Area',
            'Accessible Toilets',
            'Stage',
            'Food & Drink',
        ];

        foreach ($facilities as $index => $name) {
            $slug = Str::slug($name);

            Facility::query()->updateOrCreate(
                [
                    'organisation_id' => null,
                    'slug' => $slug,
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
