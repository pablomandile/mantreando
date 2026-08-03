<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('subir un avatar lo guarda y reemplaza el anterior', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/avatar', [
        'avatar' => UploadedFile::fake()->image('cara.jpg', 200, 200),
    ])->assertRedirect();

    $first = $user->refresh()->avatar;
    expect($first)->not->toBeNull();
    Storage::disk('public')->assertExists($first);

    $this->actingAs($user)->post('/settings/avatar', [
        'avatar' => UploadedFile::fake()->image('otra.jpg', 200, 200),
    ]);

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->refresh()->avatar);
});

test('quitar el avatar lo borra del disco y de la cuenta', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/avatar', [
        'avatar' => UploadedFile::fake()->image('cara.jpg'),
    ]);
    $path = $user->refresh()->avatar;

    $this->actingAs($user)->delete('/settings/avatar')->assertRedirect();

    expect($user->refresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('un avatar externo (Google) se puede quitar sin tocar el disco', function () {
    $user = User::factory()->googleUser()->create();

    $this->actingAs($user)->delete('/settings/avatar');

    expect($user->refresh()->avatar)->toBeNull();
});

test('el avatar valida que sea imagen de hasta 2MB', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/avatar', [
        'avatar' => UploadedFile::fake()->create('doc.pdf', 100),
    ])->assertSessionHasErrors('avatar');

    $this->actingAs($user)->post('/settings/avatar', [
        'avatar' => UploadedFile::fake()->image('grande.jpg')->size(3000),
    ])->assertSessionHasErrors('avatar');
});

test('avatar_url resuelve paths locales y URLs externas', function () {
    $local = User::factory()->create(['avatar' => 'avatars/1/foto.jpg']);
    $external = User::factory()->googleUser()->create();
    $none = User::factory()->create(['avatar' => null]);

    expect($local->avatar_url)->toContain('storage/avatars/1/foto.jpg')
        ->and($external->avatar_url)->toStartWith('http')
        ->and($none->avatar_url)->toBeNull();
});

test('el perfil acepta locale es o en y rechaza otros', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => 'en',
    ]);
    expect($user->refresh()->locale)->toBe('en');

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => 'fr',
    ])->assertSessionHasErrors('locale');
});

test('la cookie appearance sincroniza users.theme silenciosamente', function () {
    $user = User::factory()->create(['theme' => 'system']);

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', 'dark')
        ->get('/practice');

    expect($user->refresh()->theme)->toBe('dark');
});

test('las preferencias de práctica se guardan en settings JSON', function () {
    $user = User::factory()->create(['settings' => ['otra_clave' => 'x']]);

    $this->actingAs($user)->patch('/settings/practice', [
        'haptics_enabled' => false,
        'sound_enabled' => true,
        'default_mode' => 'assisted',
    ])->assertRedirect();

    $settings = $user->refresh()->settings;
    expect($settings['haptics_enabled'])->toBeFalse()
        ->and($settings['sound_enabled'])->toBeTrue()
        ->and($settings['default_mode'])->toBe('assisted')
        ->and($settings['otra_clave'])->toBe('x'); // el merge no pisa otras claves
});

test('las preferencias de práctica validan el modo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/practice', [
        'haptics_enabled' => true,
        'sound_enabled' => false,
        'default_mode' => 'zen',
    ])->assertSessionHasErrors('default_mode');
});

test('la página de preferencias de práctica renderiza con defaults', function () {
    $user = User::factory()->create(['settings' => null]);

    $this->actingAs($user)->get('/settings/practice')->assertOk();
});
