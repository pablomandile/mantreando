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
