<?php

use App\Models\Mantra;
use App\Models\User;

test('un guest recibe 401', function () {
    $this->getJson('/api/v1/practice/bootstrap')->assertUnauthorized();
});

test('devuelve mantras del sistema y propios, nunca ajenos', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $system = Mantra::factory()->create(['name' => 'Om Mani Padme Hum']);
    $own = Mantra::factory()->ownedBy($user)->create(['name' => 'Mi mantra']);
    Mantra::factory()->ownedBy($other)->create(['name' => 'Ajeno']);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk();

    $ids = collect($response->json('data.mantras'))->pluck('id');
    expect($ids)->toContain($system->id)
        ->toContain($own->id)
        ->and($ids)->toHaveCount(2);
});

test('incluye defaults de pivot cuando el usuario no configuró nada', function () {
    $user = User::factory()->create();
    Mantra::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.mantras.0.pivot.is_favorite', false)
        ->assertJsonPath('data.mantras.0.pivot.daily_commitment', null)
        ->assertJsonPath('data.mantras.0.pivot.total_goal', null);
});

test('incluye las preferencias del pivot cuando existen', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $user->mantras()->attach($mantra->id, [
        'is_favorite' => true,
        'daily_commitment' => 108,
        'total_goal' => 100000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.mantras.0.pivot.is_favorite', true)
        ->assertJsonPath('data.mantras.0.pivot.daily_commitment', 108)
        ->assertJsonPath('data.mantras.0.pivot.total_goal', 100000);
});

test('devuelve el usuario con timezone, locale, theme y settings', function () {
    $user = User::factory()->create([
        'timezone' => 'America/Argentina/Buenos_Aires',
        'locale' => 'es',
        'theme' => 'dark',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.timezone', 'America/Argentina/Buenos_Aires')
        ->assertJsonPath('data.user.locale', 'es')
        ->assertJsonPath('data.user.theme', 'dark');

    expect($response->json('data.server_time'))->not->toBeNull();
});

test('la categoría viaja con nombre localizado', function () {
    $user = User::factory()->create();
    Mantra::factory()->create();

    app()->setLocale('es');

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    expect($response->json('data.mantras.0.category.name'))->toBeString()->not->toBeEmpty();
});
