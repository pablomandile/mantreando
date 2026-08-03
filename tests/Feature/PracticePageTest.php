<?php

use App\Models\User;

test('la página de práctica renderiza para un usuario autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/practice')
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

test('la pantalla de práctica renderiza con un mantra accesible', function () {
    $user = User::factory()->create();
    $mantra = \App\Models\Mantra::factory()->create();

    $this->actingAs($user)
        ->get("/practice/session/{$mantra->id}")
        ->assertOk();
});

test('la pantalla de práctica rechaza mantras ajenos', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = \App\Models\Mantra::factory()->ownedBy($other)->create();

    $this->actingAs($user)
        ->get("/practice/session/{$foreign->id}")
        ->assertForbidden();
});

test('el bootstrap incluye el progreso de hoy', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.today.total', 0)
        ->assertJsonStructure(['data' => ['today' => ['local_date', 'total', 'by_mantra']]]);
});
