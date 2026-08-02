<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function fakeGoogleUser(array $overrides = []): SocialiteUser
{
    $defaults = [
        'id' => '108977312345678901234',
        'email' => 'practicante@gmail.com',
        'name' => 'Practicante Devoto',
        'avatar' => 'https://lh3.googleusercontent.com/a/avatar.jpg',
    ];
    $data = [...$defaults, ...$overrides];

    $user = Mockery::mock(SocialiteUser::class);
    $user->shouldReceive('getId')->andReturn($data['id']);
    $user->shouldReceive('getEmail')->andReturn($data['email']);
    $user->shouldReceive('getName')->andReturn($data['name']);
    $user->shouldReceive('getAvatar')->andReturn($data['avatar']);

    return $user;
}

function mockGoogleCallback(SocialiteUser $user): void
{
    config(['services.google.client_id' => 'test-client-id']);
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($user);
}

test('crea un usuario nuevo sin password desde google', function () {
    mockGoogleCallback(fakeGoogleUser());

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('practice.index'));

    $user = User::where('email', 'practicante@gmail.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->google_id)->toBe('108977312345678901234')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/a/avatar.jpg')
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('linkea una cuenta existente por email sin tocar su password', function () {
    $existing = User::factory()->create(['email' => 'practicante@gmail.com']);
    $originalPassword = $existing->password;

    mockGoogleCallback(fakeGoogleUser());

    $this->get('/auth/google/callback')->assertRedirect(route('practice.index'));

    $existing->refresh();
    expect($existing->google_id)->toBe('108977312345678901234')
        ->and($existing->password)->toBe($originalPassword)
        ->and($existing->avatar)->toBe('https://lh3.googleusercontent.com/a/avatar.jpg');

    $this->assertAuthenticatedAs($existing);
});

test('matchea un usuario que vuelve por google_id aunque cambie el email', function () {
    $existing = User::factory()->googleUser()->create([
        'google_id' => '108977312345678901234',
        'email' => 'viejo@gmail.com',
    ]);

    mockGoogleCallback(fakeGoogleUser());

    $this->get('/auth/google/callback')->assertRedirect(route('practice.index'));

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1);
});

test('un fallo del provider redirige al login con error', function () {
    config(['services.google.client_id' => 'test-client-id']);
    Socialite::shouldReceive('driver')->with('google')->andThrow(new RuntimeException('provider caido'));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('el redirect sin client_id configurado vuelve al login con error', function () {
    config(['services.google.client_id' => null]);

    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('un usuario autenticado no accede a las rutas de google', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/auth/google/redirect')->assertRedirect();
});
