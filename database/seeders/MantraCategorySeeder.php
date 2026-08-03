<?php

namespace Database\Seeders;

use App\Models\MantraCategory;
use Illuminate\Database\Seeder;

class MantraCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'compassion', 'position' => 1, 'name' => ['es' => 'Compasión', 'en' => 'Compassion']],
            ['slug' => 'wisdom', 'position' => 2, 'name' => ['es' => 'Sabiduría', 'en' => 'Wisdom']],
            ['slug' => 'purification', 'position' => 3, 'name' => ['es' => 'Purificación', 'en' => 'Purification']],
            ['slug' => 'healing', 'position' => 4, 'name' => ['es' => 'Sanación', 'en' => 'Healing']],
            ['slug' => 'protection', 'position' => 5, 'name' => ['es' => 'Protección', 'en' => 'Protection']],
            ['slug' => 'tantra', 'position' => 6, 'name' => ['es' => 'Tantra', 'en' => 'Tantra']],
            ['slug' => 'guru-yoga', 'position' => 7, 'name' => ['es' => 'Guru yoga', 'en' => 'Guru yoga']],
        ];

        foreach ($categories as $category) {
            MantraCategory::updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'position' => $category['position']],
            );
        }
    }
}
