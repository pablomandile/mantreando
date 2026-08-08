<?php

use App\Models\Recitation;
use App\Models\RecitationLog;
use App\Models\User;
use Database\Seeders\SystemRecitationSeeder;

test('la pagina de recitaciones pide sesion', function () {
    $this->get('/recitations')->assertRedirect('/login');
});

test('el seed carga las 10 recitaciones en orden', function () {
    $this->seed(SystemRecitationSeeder::class);

    expect(Recitation::count())->toBe(10);

    $titles = Recitation::orderBy('position')->pluck('title')->all();

    expect($titles[0])->toBe('Yoga conciso de las seis sesiones')
        ->and($titles[1])->toBe('Los cuatro inconmensurables')
        ->and($titles[2])->toBe('Promesa')
        ->and(end($titles))->toBe('Los ocho versos de alabanza a la Madre');
});

test('cada recitacion trae color y dos seguidas no lo repiten', function () {
    $this->seed(SystemRecitationSeeder::class);

    $recitations = Recitation::orderBy('position')->get();

    expect($recitations->filter(fn ($r) => $r->color === null))->toBeEmpty();

    $colors = $recitations->pluck('color')->map(fn ($c) => $c->value)->all();

    for ($i = 1; $i < count($colors); $i++) {
        // Los ocho versos (sánscrito y español) comparten color a propósito:
        // son el mismo texto, y es la única repetición seguida permitida.
        $pareja = str_contains($recitations[$i]->slug, 'ocho-versos');

        expect($colors[$i] === $colors[$i - 1] && ! $pareja)->toBeFalse(
            "'{$recitations[$i]->title}' repite el color de la tarjeta anterior",
        );
    }
});

test('la pagina manda el color de cada recitacion', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/recitations');
    $props = collect($response->viewData('page')['props']['recitations']);

    expect($props->whereNull('color'))->toBeEmpty()
        ->and($props->firstWhere('title', 'El yoga del despertar')['color'])->toBe('orange');
});

test('el seed es idempotente: correrlo dos veces no duplica', function () {
    $this->seed(SystemRecitationSeeder::class);
    $this->seed(SystemRecitationSeeder::class);

    expect(Recitation::count())->toBe(10);
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

    // El del despertar va junto al del dormir, que es su par
    $orden = Recitation::orderBy('position')->pluck('slug')->all();
    expect(array_search('yoga-del-despertar', $orden, true))
        ->toBe(array_search('yoga-del-dormir', $orden, true) + 1);

    $nectar = Recitation::where('slug', 'yoga-experimentar-nectar')->first();
    expect($nectar->text)->toContain('Néctar que cura las enfermedades')
        ->and($nectar->text)->not->toContain('que que');

    // Ninguna arrastra la "j" que el OCR dejo en lugar de "¡"
    foreach (Recitation::all() as $recitation) {
        expect($recitation->text)->not->toContain('jOh');
    }
});

test('el compromiso se fija, se cambia y se quita', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();
    $recitation = Recitation::first();

    $this->actingAs($user)
        ->patch("/recitations/{$recitation->id}/commitment", ['daily_commitment' => 3])
        ->assertRedirect();

    expect($user->recitations()->first()->pivot->daily_commitment)->toBe(3);

    $this->actingAs($user)
        ->patch("/recitations/{$recitation->id}/commitment", ['daily_commitment' => 6])
        ->assertRedirect();

    expect($user->recitations()->first()->pivot->daily_commitment)->toBe(6);

    $this->actingAs($user)
        ->patch("/recitations/{$recitation->id}/commitment", ['daily_commitment' => null])
        ->assertRedirect();

    expect($user->recitations()->first()->pivot->daily_commitment)->toBeNull();
});

test('registrar suma a lo que ya hay ese dia', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();
    $recitation = Recitation::first();

    $this->actingAs($user)
        ->post("/recitations/{$recitation->id}/log", ['count' => 2, 'local_date' => '2026-08-04'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post("/recitations/{$recitation->id}/log", ['count' => 3, 'local_date' => '2026-08-04'])
        ->assertRedirect();

    $log = RecitationLog::where('user_id', $user->id)
        ->where('recitation_id', $recitation->id)
        ->where('local_date', '2026-08-04')
        ->first();

    expect($log->count)->toBe(5)
        ->and(RecitationLog::count())->toBe(1); // una fila por dia, no una por registro
});

test('cada dia lleva su propia cuenta', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();
    $recitation = Recitation::first();

    $this->actingAs($user)->post("/recitations/{$recitation->id}/log", ['count' => 4, 'local_date' => '2026-08-03']);
    $this->actingAs($user)->post("/recitations/{$recitation->id}/log", ['count' => 1, 'local_date' => '2026-08-04']);

    expect(RecitationLog::where('local_date', '2026-08-03')->first()->count)->toBe(4)
        ->and(RecitationLog::where('local_date', '2026-08-04')->first()->count)->toBe(1);
});

test('la cuenta de recitaciones no toca la de los mantras', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();
    $recitation = Recitation::first();

    $this->actingAs($user)->post("/recitations/{$recitation->id}/log", ['count' => 9]);

    // Nada en las tablas de la practica del mala
    expect($user->practiceSessions()->count())->toBe(0)
        ->and($user->dailyAggregates()->count())->toBe(0)
        ->and($user->mantras()->count())->toBe(0);
});

test('la pagina trae el compromiso y lo recitado hoy', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $recitation = Recitation::first();
    $hoy = now($user->timezone)->toDateString();

    $this->actingAs($user)->patch("/recitations/{$recitation->id}/commitment", ['daily_commitment' => 3]);
    $this->actingAs($user)->post("/recitations/{$recitation->id}/log", ['count' => 2, 'local_date' => $hoy]);

    $response = $this->actingAs($user)->get("/recitations?local_date={$hoy}");
    $props = collect($response->viewData('page')['props']['recitations'])
        ->firstWhere('id', $recitation->id);

    expect($props['daily_commitment'])->toBe(3)
        ->and($props['today_count'])->toBe(2);
});

test('registrar con cantidad invalida se rechaza', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();
    $recitation = Recitation::first();

    $this->actingAs($user)
        ->post("/recitations/{$recitation->id}/log", ['count' => 0])
        ->assertSessionHasErrors('count');

    $this->actingAs($user)
        ->post("/recitations/{$recitation->id}/log", ['count' => -5])
        ->assertSessionHasErrors('count');

    expect(RecitationLog::count())->toBe(0);
});

test('un usuario autenticado ve las recitaciones', function () {
    $this->seed(SystemRecitationSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/recitations');

    $response->assertOk();

    $titles = collect($response->viewData('page')['props']['recitations'])
        ->pluck('title');

    expect($titles)->toHaveCount(10)
        ->and($titles)->toContain('Promesa')
        ->and($titles)->toContain('El yoga del despertar');
});
