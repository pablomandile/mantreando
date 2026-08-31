<?php

namespace Database\Seeders;

use App\Models\RetreatDeity;
use Illuminate\Database\Seeder;

class RetreatDeitySeeder extends Seeder
{
    /**
     * Las deidades con las que arranca el Retiro de aproximación. El upsert va
     * por slug, así que corregir un nombre o una imagen acá actualiza la fila
     * en vez de duplicarla.
     *
     * Se siembran SIN mantras a propósito: el texto de cada etapa y su cifra
     * (100.000 de uno, 10.000 de la sílaba) los carga el administrador desde
     * la app, que es la razón de que exista esa pantalla. Sembrar cifras
     * inventadas sería peor que dejarlas vacías.
     *
     * La imagen sale de las láminas que ya trae la app donde existe; las dos
     * que faltan quedan en null hasta que el admin las suba.
     */
    public function run(): void
    {
        $deities = [
            ['slug' => 'heruka', 'name' => 'Heruka', 'image' => 'heruka', 'color' => 'blue'],
            ['slug' => 'vajrayoguini', 'name' => 'Vajrayoguini', 'image' => 'vajrayoguini', 'color' => 'red'],
            ['slug' => 'guru-sumati-buda-heruka', 'name' => 'Guru Sumati Buda Heruka', 'image' => null, 'color' => 'amber'],
            ['slug' => 'migtsema', 'name' => 'Migtsema', 'image' => null, 'color' => 'orange'],
            ['slug' => 'vajrasattva', 'name' => 'Vajrasattva', 'image' => 'vajrasatva', 'color' => 'neutral'],
            ['slug' => 'dorje-shugden', 'name' => 'Dorje Shugden', 'image' => 'dorje-shugden', 'color' => 'indigo'],
        ];

        foreach ($deities as $position => $deity) {
            RetreatDeity::updateOrCreate(
                ['slug' => $deity['slug']],
                [
                    'name' => $deity['name'],
                    'color' => $deity['color'],
                    'image_path' => $deity['image'] === null ? null : "img/budas/{$deity['image']}.jpg",
                    'position' => $position + 1,
                ],
            );
        }
    }
}
