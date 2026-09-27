<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    /**
     * Seed system event categories (organisation_id = null).
     */
    public function run(): void
    {
        $categories = [
            'Live Music',
            'Comedy',
            'Parties & Celebrations',
            'Food & Drink',
            'Family & Kids',
            'Community',
            'Sports',
            'Business & Networking',
            'Workshops & Classes',
            'Entertainment',
            'Seasonal & Special Events',
            'Other',
        ];

        foreach ($categories as $name) {
            EventCategory::query()->firstOrCreate(
                [
                    'organisation_id' => null,
                    'name' => $name,
                ],
            );
        }
    }
}
