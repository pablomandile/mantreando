<?php

use App\Models\Mantra;
use App\Models\User;
use Database\Seeders\MantraCategorySeeder;
use Database\Seeders\SystemMantraSeeder;

test('el middleware aplica el locale del usuario autenticado', function () {
    $userEs = User::factory()->create(['locale' => 'es']);
    $userEn = User::factory()->create(['locale' => 'en']);

    $this->actingAs($userEs)->get('/practice');
    expect(app()->getLocale())->toBe('es');

    $this->actingAs($userEn)->get('/practice');
    expect(app()->getLocale())->toBe('en');
});

test('un locale inválido guardado no rompe (queda el default)', function () {
    $user = User::factory()->create();
    $user->forceFill(['locale' => 'xx'])->save();

    $this->actingAs($user)->get('/practice')->assertOk();
    expect(app()->getLocale())->toBe('es');
});

test('el html lang refleja el locale del usuario', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->get('/practice')->assertSee('<html lang="en"', false);
});

test('los mantras del sistema se sirven traducidos según el locale', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    $userEn = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($userEn)->getJson('/api/v1/practice/bootstrap');
    $names = collect($response->json('data.mantras'))->pluck('name');

    expect($names)->toContain('Green Tara')
        ->and($names)->toContain('Medicine Buddha')
        ->and($names)->toContain('Vajrasattva (long)');

    $userEs = User::factory()->create(['locale' => 'es']);
    $namesEs = collect(
        $this->actingAs($userEs)->getJson('/api/v1/practice/bootstrap')->json('data.mantras'),
    )->pluck('name');

    expect($namesEs)->toContain('Tara Verde')
        ->and($namesEs)->toContain('Gueshe Kelsang Gyatso');
});

test('los mantras de usuario nunca se traducen', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $own = Mantra::factory()->ownedBy($user)->create(['name' => 'Mi mantra propio']);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');
    $names = collect($response->json('data.mantras'))->pluck('name');

    expect($names)->toContain('Mi mantra propio');
});

test('las categorías se localizan por locale', function () {
    $this->seed(MantraCategorySeeder::class);

    $userEn = User::factory()->create(['locale' => 'en']);
    $response = $this->actingAs($userEn)->get('/mantras');

    $categories = collect($response->viewData('page')['props']['categories'])->pluck('name');
    expect($categories)->toContain('Wisdom')->toContain('Healing');
});

test('en.json es JSON válido y cubre las claves con placeholders', function () {
    $lang = json_decode(file_get_contents(base_path('lang/en.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($lang)->toBeArray()->not->toBeEmpty();

    // Toda clave con :placeholder debe conservarlo en la traducción
    foreach ($lang as $key => $value) {
        preg_match_all('/:\w+/', $key, $matches);

        foreach ($matches[0] as $placeholder) {
            expect($value)->toContain($placeholder);
        }
    }
});
