<?php

use App\Models\Mantra;
use App\Models\User;

test('la página Objetivo renderiza con el default de 108', function () {
    $response = $this->actingAs(User::factory()->create(['settings' => null]))
        ->get('/goal');

    $response->assertOk();
    expect($response->viewData('page')['props']['dailyGoal'])->toBe(108)
        ->and($response->viewData('page')['props']['totalGoal'])->toBeNull();
});

test('guardar el objetivo diario y global persiste en settings', function () {
    $user = User::factory()->create(['settings' => ['haptics_enabled' => false]]);

    $this->actingAs($user)->patch('/goal', [
        'daily_goal' => 21,
        'total_goal' => 100000,
    ])->assertRedirect();

    $settings = $user->refresh()->settings;
    expect($settings['daily_goal'])->toBe(21)
        ->and($settings['total_goal'])->toBe(100000)
        ->and($settings['haptics_enabled'])->toBeFalse(); // el merge no pisa
});

test('el objetivo global es opcional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/goal', [
        'daily_goal' => 54,
        'total_goal' => null,
    ])->assertRedirect();

    expect($user->refresh()->settings['daily_goal'])->toBe(54);
});

test('el objetivo diario valida enteros positivos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/goal', ['daily_goal' => 0])
        ->assertSessionHasErrors('daily_goal');

    $this->actingAs($user)->patch('/goal', ['daily_goal' => 'muchos'])
        ->assertSessionHasErrors('daily_goal');
});

test('el objetivo diario viaja a la isla vía bootstrap (settings)', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch('/goal', ['daily_goal' => 21]);

    $this->actingAs($user)->getJson('/api/v1/practice/bootstrap')
        ->assertJsonPath('data.user.settings.daily_goal', 21);
});

test('reordenar guarda posiciones y el índice las respeta', function () {
    $user = User::factory()->create();
    $a = Mantra::factory()->create(['name' => 'AAA Primero alfabético']);
    $b = Mantra::factory()->create(['name' => 'ZZZ Último alfabético']);

    // Invertir el orden alfabético: Z primero
    $this->actingAs($user)->post('/mantras/reorder', [
        'ids' => [$b->id, $a->id],
    ])->assertRedirect();

    $names = collect(
        $this->actingAs($user)->get('/mantras')->viewData('page')['props']['mantras'],
    )->pluck('name');

    expect($names->first())->toBe('ZZZ Último alfabético');

    // El bootstrap de la isla respeta el mismo orden (índice sort)
    $sorted = collect(
        $this->actingAs($user)->getJson('/api/v1/practice/bootstrap')->json('data.mantras'),
    )->sortBy('sort')->pluck('name')->values();

    expect($sorted->first())->toBe('ZZZ Último alfabético');
});

test('reordenar ignora mantras ajenos', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Mantra::factory()->ownedBy($other)->create();
    $own = Mantra::factory()->create();

    $this->actingAs($user)->post('/mantras/reorder', [
        'ids' => [$foreign->id, $own->id],
    ])->assertRedirect();

    // El ajeno no recibió fila pivot del usuario
    expect($user->mantras()->find($foreign->id))->toBeNull()
        ->and($user->mantras()->find($own->id)->pivot->position)->toBe(2);
});
