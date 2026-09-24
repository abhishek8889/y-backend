<?php

namespace Database\Seeders;

use App\Models\VenueType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenueTypeSeeder extends Seeder
{
    /**
     * Seed system venue types.
     */
    public function run(): void
    {
        $types = [
            'Stadium',
            'Sports Ground',
            'Sports Centre',
            'Football Ground',
            'Indoor Arena',
            'Outdoor Venue',
            'Community Centre',
            'Event Hall',
            'Other',
        ];

        foreach ($types as $index => $name) {
            VenueType::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
