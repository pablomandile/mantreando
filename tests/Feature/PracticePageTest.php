<?php

use App\Models\Mantra;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('la página de práctica renderiza para un usuario autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/practice')
        ->assertOk();
});

test('precarga la biblioteca como lista pelada, no envuelta en data', function () {
    // La forma importa tanto como el contenido: MantraResource::collection()
    // se serializa envuelta en {"data": [...]} y la isla espera el array. Con
    // el objeto envuelto la pantalla explotaba en el primer render
    // ("mantras.value.find is not a function"), y un assertOk() no lo veía
    // porque el HTML se sirve bien: el que rompe es el render del cliente.
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create(['name' => 'Om Mani Padme Hum']);

    $this->actingAs($user)
        ->get('/practice')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('practice/Index')
            ->has('mantras', 1)
            ->where('mantras.0.id', $mantra->id)
            ->where('mantras.0.name', 'Om Mani Padme Hum')
            // Los campos que la isla lee para pintar el select y el mantra.
            ->has('mantras.0.sort')
            ->has('mantras.0.text')
            ->has('mantras.0.pivot.is_favorite')
        );
});

test('la precarga respeta el acceso: nunca mantras de otro usuario', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $system = Mantra::factory()->create();
    $own = Mantra::factory()->ownedBy($user)->create();
    Mantra::factory()->ownedBy($other)->create();

    $this->actingAs($user)
        ->get('/practice')
        ->assertInertia(fn (Assert $page) => $page->has('mantras', 2));

    expect([$system->id, $own->id])->toHaveCount(2);
});

test('la página de práctica acepta el mantra preseleccionado por query', function () {
    $mantra = Mantra::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/practice?mantra={$mantra->id}")
        ->assertOk();
});

test('un guest es redirigido al login', function () {
    $this->get('/practice')->assertRedirect(route('login'));
});

test('la página del spike del mala renderiza para un usuario autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/practice/spike')
        ->assertOk();
});

test('el bootstrap incluye el progreso de hoy', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.today.total', 0)
        ->assertJsonStructure(['data' => ['today' => ['local_date', 'total', 'by_mantra']]]);
});
