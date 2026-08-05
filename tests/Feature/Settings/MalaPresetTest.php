<?php

use App\Models\MalaPreset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('elegir un material crea el preset activo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'bodhi',
    ])->assertRedirect();

    $preset = MalaPreset::where('user_id', $user->id)->where('is_active', true)->first();
    expect($preset->material)->toBe('bodhi');
});

test('cambiar el material actualiza el mismo preset', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', ['material' => 'red']);
    $this->actingAs($user)->post('/settings/mala', ['material' => 'blue']);

    expect(MalaPreset::where('user_id', $user->id)->count())->toBe(1)
        ->and(MalaPreset::where('user_id', $user->id)->first()->material)->toBe('blue');
});

test('elegir un color de borla lo guarda', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'tassel_color' => 'jade',
    ])->assertRedirect();

    expect(MalaPreset::where('user_id', $user->id)->first()->tassel_color)->toBe('jade');
});

test('la borla vuelve a seguir al material cuando se manda vacío', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'tassel_color' => 'crimson',
    ]);
    // '' es lo que manda "Como las cuentas": tiene que quedar null, no ''.
    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'tassel_color' => '',
    ]);

    expect(MalaPreset::where('user_id', $user->id)->first()->tassel_color)->toBeNull();
});

test('un color de borla inválido se rechaza', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'tassel_color' => 'fucsia',
    ])->assertSessionHasErrors('tassel_color');
});

test('el bootstrap le pasa el color de la borla a la isla', function () {
    $user = User::factory()->create();
    MalaPreset::create([
        'user_id' => $user->id,
        'material' => 'wood',
        'tassel_color' => 'indigo',
        'is_active' => true,
    ]);

    $this->actingAs($user)->getJson('/api/v1/practice/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.mala_preset.tassel_color', 'indigo');
});

test('un material inválido se rechaza', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', ['material' => 'oro'])
        ->assertSessionHasErrors('material');
});

test('subir una textura la guarda y reemplazar borra la anterior', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'texture' => UploadedFile::fake()->image('madera.jpg', 300, 300),
    ]);

    $first = MalaPreset::where('user_id', $user->id)->first()->texture_path;
    Storage::disk('public')->assertExists($first);

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'texture' => UploadedFile::fake()->image('otra.jpg', 300, 300),
    ]);

    Storage::disk('public')->assertMissing($first);
});

test('quitar la textura vuelve al material', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'texture' => UploadedFile::fake()->image('madera.jpg'),
    ]);
    $path = MalaPreset::where('user_id', $user->id)->first()->texture_path;

    $this->actingAs($user)->post('/settings/mala', [
        'material' => 'wood',
        'remove_texture' => true,
    ]);

    expect(MalaPreset::where('user_id', $user->id)->first()->texture_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('el bootstrap incluye el preset activo (o wood por defecto)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/practice/bootstrap')
        ->assertJsonPath('data.mala_preset.material', 'wood')
        ->assertJsonPath('data.mala_preset.texture_url', null);

    $this->actingAs($user)->post('/settings/mala', ['material' => 'blue']);

    $this->actingAs($user)->getJson('/api/v1/practice/bootstrap')
        ->assertJsonPath('data.mala_preset.material', 'blue');
});

test('la página de personalización renderiza con los props que usa', function () {
    // assertOk() sola no alcanza: el HTML se sirve igual con un prop de la
    // forma equivocada, y el que rompe es el render del cliente.
    $user = User::factory()->create();
    MalaPreset::create([
        'user_id' => $user->id,
        'material' => 'bodhi',
        'tassel_color' => 'rose',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/settings/mala')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Mala')
            ->where('preset.material', 'bodhi')
            ->where('preset.tassel_color', 'rose')
            ->where('preset.texture_url', null)
            // Listas planas: la UI las recorre con v-for.
            ->has('materials', 4)
            ->has('tasselColors', 6)
            ->where('tasselColors.0', 'saffron')
        );
});

test('sin preset guardado la página cae en los defaults', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/mala')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preset.material', 'wood')
            ->where('preset.tassel_color', null)
        );
});
