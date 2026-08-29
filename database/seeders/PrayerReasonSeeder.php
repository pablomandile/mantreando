<?php

namespace Database\Seeders;

use App\Models\PrayerReason;
use Illuminate\Database\Seeder;

class PrayerReasonSeeder extends Seeder
{
    /**
     * Los motivos con los que arranca la Lista de oración. El upsert va por
     * slug, así que corregir un nombre o un color acá actualiza la fila en vez
     * de duplicarla.
     *
     * Un administrador puede sumar más desde la app; estos tres son el piso
     * que trae la instalación.
     */
    public function run(): void
    {
        $reasons = [
            ['slug' => 'paz-mental', 'name' => 'Paz mental', 'color' => 'blue'],
            ['slug' => 'renacimiento-afortunado', 'name' => 'Renacimiento afortunado', 'color' => 'violet'],
            ['slug' => 'recuperacion', 'name' => 'Recuperación', 'color' => 'green'],
        ];

        foreach ($reasons as $position => $reason) {
            PrayerReason::updateOrCreate(
                ['slug' => $reason['slug']],
                [...$reason, 'position' => $position + 1],
            );
        }
    }
}
