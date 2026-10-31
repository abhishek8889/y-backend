<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
            $slug = Str::slug($name);

            EventCategory::query()->updateOrCreate(
                [
                    'organisation_id' => null,
                    'slug' => $slug,
                ],
                [
                    'name' => $name,
                ],
            );
        }
    }
}
