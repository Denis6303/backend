<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Concert', 'name_en' => 'Concert', 'description' => 'Music concerts and live shows.'],
            ['name' => 'Cinema', 'name_en' => 'Cinema', 'description' => 'Movie screenings and film events.'],
            ['name' => 'Culture', 'name_en' => 'Culture', 'description' => 'Cultural events, exhibitions and shows.'],
            ['name' => 'Formation', 'name_en' => 'Training', 'description' => 'Trainings, workshops and courses.'],
            ['name' => 'Soiree', 'name_en' => 'Party', 'description' => 'Evening parties and nightlife events.'],
            ['name' => 'Sport', 'name_en' => 'Sport', 'description' => 'Sport events and competitions.'],
            ['name' => 'Religieux', 'name_en' => 'Religious', 'description' => 'Religious events and gatherings.'],
            ['name' => 'Gastronomie', 'name_en' => 'Food', 'description' => 'Food, cooking and gastronomy events.'],
            ['name' => 'Business', 'name_en' => 'Business', 'description' => 'Business and networking events.'],
            ['name' => 'Autre', 'name_en' => 'Other', 'description' => 'Other types of events.'],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['name' => $data['name']],
                [
                    'name_en' => $data['name_en'],
                    'description' => $data['description'],
                ]
            );
        }
    }
}
