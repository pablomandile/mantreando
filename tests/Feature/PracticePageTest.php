<?php

use App\Models\User;

test('la página de práctica renderiza para un usuario autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/practice')
        ->assertOk();
});

test('la página de práctica acepta el mantra preseleccionado por query', function () {
    $mantra = \App\Models\Mantra::factory()->create();

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
