<?php

namespace Database\Seeders;

use App\Models\VenueSuitableForOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenueSuitableForOptionSeeder extends Seeder
{
    /**
     * Seed system suitable-for options (organisation_id = null).
     */
    public function run(): void
    {
        $options = [
            'Parties',
            'Meetings',
            'Corporate Events',
            'Live Music',
            'Weddings',
            'Other',
        ];

        foreach ($options as $index => $name) {
            $slug = Str::slug($name);

            VenueSuitableForOption::query()->updateOrCreate(
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
