<?php

use App\Models\Recitation;
use App\Models\User;
use Database\Seeders\SystemRecitationSeeder;

test('la pagina de recitaciones pide sesion', function () {
    $this->get('/recitations')->assertRedirect('/login');
});

test('el seed carga las 9 recitaciones en orden', function () {
    $this->seed(SystemRecitationSeeder::class);

    expect(Recitation::count())->toBe(9);

    $titles = Recitation::orderBy('position')->pluck('title')->all();

    expect($titles[0])->toBe('Yoga conciso de las seis sesiones')
        ->and($titles[1])->toBe('Los cuatro inconmensurables')
        ->and($titles[2])->toBe('Promesa')
        ->and(end($titles))->toBe('Los ocho versos de alabanza a la Madre');
});

test('el seed es idempotente: correrlo dos veces no duplica', function () {
    $this->seed(SystemRecitationSeeder::class);
    $this->seed(SystemRecitationSeeder::class);

    expect(Recitation::count())->toBe(9);
});

test('los textos conservan su estructura de versos y no traen erratas', function () {
    $this->seed(SystemRecitationSeeder::class);

    $votos = Recitation::where('slug', 'votos-del-bodhisatva')->first();
    expect($votos->text)->toStartWith('¡Oh, Guru Buda Shakyamuni!')
        ->and($votos->text)->toContain("\n"); // versos, no un parrafo suelto

    $dormir = Recitation::where('slug', 'yoga-del-dormir')->first();
    expect($dormir->text)->toContain('luz clara del gozo')
        ->and($dormir->text)->not->toContain('ciara')
        ->and($dormir->text)->not->toContain('tos fenomenos');

    $nectar = Recitation::where('slug', 'yoga-experimentar-nectar')->first();
    expect($nectar->text)->toContain('Néctar que cura las enfermedades')
        ->and($nectar->text)->not->toContain('que que');

    // Ninguna arrastra la "j" que el OCR dejo en lugar de "¡"
    foreach (Recitation::all() as $recitation) {
        expect($recitation->text)->not->toContain('jOh');
    }
});

test('un usuario autenticado ve las recitaciones', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/recitations');

    $response->assertOk();

    $titles = collect($response->viewData('page')['props']['recitations'])
        ->pluck('title');

    expect($titles)->toHaveCount(9)
        ->and($titles)->toContain('Promesa');
});
