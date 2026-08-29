<?php

use App\Models\PrayerIntention;
use App\Models\PrayerReason;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('un admin crea un motivo y queda para todas las cuentas', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->from('/prayers/reasons')
        ->post('/prayers/reasons', ['name' => 'Larga vida del maestro'])
        ->assertRedirect('/prayers/reasons');

    $reason = PrayerReason::first();

    expect($reason->name)->toBe('Larga vida del maestro')
        ->and($reason->slug)->toBe('larga-vida-del-maestro');

    // Cualquier usuario lo ve como opción al cargar una persona.
    $this->actingAs(User::factory()->create())
        ->get('/prayers/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reasons.0.name', 'Larga vida del maestro')
            ->etc());
});

test('un admin corrige el nombre sin que cambie el slug', function () {
    $reason = PrayerReason::factory()->create(['slug' => 'recuperacion', 'name' => 'Recuperacion']);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->from('/prayers/reasons')
        ->patch("/prayers/reasons/{$reason->id}", ['name' => 'Recuperacion de la salud'])
        ->assertRedirect('/prayers/reasons');

    expect($reason->fresh()->name)->toBe('Recuperacion de la salud')
        ->and($reason->fresh()->slug)->toBe('recuperacion');
});

test('un usuario comun no puede tocar el catalogo', function () {
    $reason = PrayerReason::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/prayers/reasons')->assertForbidden();
    $this->actingAs($user)->post('/prayers/reasons', ['name' => 'Otro motivo'])->assertForbidden();
    $this->actingAs($user)
        ->patch("/prayers/reasons/{$reason->id}", ['name' => 'Cambiado'])
        ->assertForbidden();
    $this->actingAs($user)->delete("/prayers/reasons/{$reason->id}")->assertForbidden();

    expect(PrayerReason::count())->toBe(1);
});

test('la lista solo ofrece el atajo al catalogo a un admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/prayers')
        ->assertInertia(fn (Assert $page) => $page->where('canManageReasons', false)->etc());

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/prayers')
        ->assertInertia(fn (Assert $page) => $page->where('canManageReasons', true)->etc());
});

test('un motivo en uso no se puede eliminar', function () {
    $reason = PrayerReason::factory()->create();
    $intention = PrayerIntention::factory()->ownedBy(User::factory()->create())->create();
    $intention->reasons()->attach($reason);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->from('/prayers/reasons')
        ->delete("/prayers/reasons/{$reason->id}")
        ->assertRedirect('/prayers/reasons')
        ->assertSessionHasErrors('reason');

    expect(PrayerReason::count())->toBe(1);
});

test('un motivo sin usar se elimina', function () {
    $reason = PrayerReason::factory()->create();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->from('/prayers/reasons')
        ->delete("/prayers/reasons/{$reason->id}")
        ->assertRedirect('/prayers/reasons');

    expect(PrayerReason::count())->toBe(0);
});
