<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('la página Acerca de renderiza', function () {
    $this->actingAs(User::factory()->create())
        ->get('/about')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('about/Index'));
});

test('Acerca de no exige el mail verificado', function () {
    // A propósito fuera del grupo 'verified': saber quién hizo la app no
    // depende de confirmar el mail.
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/about')->assertOk();
});

test('Acerca de pide sesión', function () {
    $this->get('/about')->assertRedirect('/login');
});
