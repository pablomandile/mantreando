<?php

use App\Models\User;

test('el registro guarda la timezone del dispositivo', function () {
    $response = $this->post('/register', [
        'name' => 'Practicante',
        'email' => 'practicante@example.com',
        'password' => 'password-segura-1',
        'password_confirmation' => 'password-segura-1',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ]);

    $response->assertRedirect();

    expect(User::where('email', 'practicante@example.com')->first()->timezone)
        ->toBe('America/Argentina/Buenos_Aires');
});

test('el registro rechaza una timezone inválida', function () {
    $response = $this->post('/register', [
        'name' => 'Practicante',
        'email' => 'practicante@example.com',
        'password' => 'password-segura-1',
        'password_confirmation' => 'password-segura-1',
        'timezone' => 'Marte/Cydonia',
    ]);

    $response->assertSessionHasErrors('timezone');
    expect(User::where('email', 'practicante@example.com')->exists())->toBeFalse();
});

test('el registro sin timezone funciona y la deja null', function () {
    $this->post('/register', [
        'name' => 'Practicante',
        'email' => 'practicante@example.com',
        'password' => 'password-segura-1',
        'password_confirmation' => 'password-segura-1',
    ]);

    expect(User::where('email', 'practicante@example.com')->first()->timezone)->toBeNull();
});

test('PATCH settings/timezone setea la timezone cuando falta', function () {
    $user = User::factory()->create(['timezone' => null]);

    $response = $this->actingAs($user)->patch('/settings/timezone', [
        'timezone' => 'Europe/Madrid',
    ]);

    $response->assertRedirect();
    expect($user->refresh()->timezone)->toBe('Europe/Madrid');
});

test('PATCH settings/timezone rechaza valores inválidos', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->patch('/settings/timezone', ['timezone' => 'no-es-una-zona'])
        ->assertSessionHasErrors('timezone');

    expect($user->refresh()->timezone)->toBeNull();
});

test('un guest no puede tocar settings/timezone', function () {
    $this->patch('/settings/timezone', ['timezone' => 'UTC'])
        ->assertRedirect(route('login'));
});

test('el perfil permite actualizar la timezone', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => 'Asia/Kathmandu',
    ]);

    expect($user->refresh()->timezone)->toBe('Asia/Kathmandu');
});
