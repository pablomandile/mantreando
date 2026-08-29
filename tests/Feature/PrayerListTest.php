<?php

use App\Models\PrayerIntention;
use App\Models\PrayerReason;
use App\Models\User;

// Sin acentos en los nombres que se buscan con assertSee: los props de
// Inertia viajan JSON-escapados y no matchean.

test('la lista muestra las propias y nunca las ajenas', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    PrayerIntention::factory()->ownedBy($user)->create(['name' => 'Ana Lopez']);
    PrayerIntention::factory()->ownedBy($other)->create(['name' => 'Persona ajena']);

    $this->actingAs($user)
        ->get('/prayers')
        ->assertOk()
        ->assertSee('Ana Lopez')
        ->assertDontSee('Persona ajena');
});

test('se agrega una persona con varios motivos', function () {
    $user = User::factory()->create();
    $reasons = PrayerReason::factory()->count(2)->create();

    $this->actingAs($user)
        ->post('/prayers', [
            'name' => 'Ana Lopez',
            'reason_ids' => $reasons->pluck('id')->all(),
        ])
        ->assertRedirect('/prayers');

    $intention = PrayerIntention::first();

    expect($intention->name)->toBe('Ana Lopez')
        ->and($intention->user_id)->toBe($user->id)
        ->and($intention->archived_at)->toBeNull()
        ->and($intention->reasons()->pluck('prayer_reasons.id')->all())
        ->toEqualCanonicalizing($reasons->pluck('id')->all());
});

test('alcanza con el motivo escrito a mano', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/prayers', [
            'name' => 'Juan Perez',
            'custom_reason' => 'Que encuentre trabajo',
        ])
        ->assertRedirect('/prayers');

    $intention = PrayerIntention::first();

    expect($intention->custom_reason)->toBe('Que encuentre trabajo')
        ->and($intention->reasons()->count())->toBe(0);
});

test('sin ningun motivo no se guarda', function () {
    $this->actingAs(User::factory()->create())
        ->post('/prayers', ['name' => 'Ana Lopez'])
        ->assertSessionHasErrors('reason_ids');

    expect(PrayerIntention::count())->toBe(0);
});

test('el nombre es obligatorio', function () {
    $reason = PrayerReason::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post('/prayers', ['name' => '', 'reason_ids' => [$reason->id]])
        ->assertSessionHasErrors('name');
});

test('editar reemplaza los motivos', function () {
    $user = User::factory()->create();
    $viejo = PrayerReason::factory()->create();
    $nuevo = PrayerReason::factory()->create();

    $intention = PrayerIntention::factory()->ownedBy($user)->create(['name' => 'Ana Lopez']);
    $intention->reasons()->attach($viejo);

    $this->actingAs($user)
        ->put("/prayers/{$intention->id}", [
            'name' => 'Ana Lopez',
            'reason_ids' => [$nuevo->id],
        ])
        ->assertRedirect('/prayers');

    expect($intention->fresh()->reasons()->pluck('prayer_reasons.id')->all())
        ->toBe([$nuevo->id]);
});

test('archivar la saca de la lista y la guarda con su fecha', function () {
    $user = User::factory()->create();
    $intention = PrayerIntention::factory()->ownedBy($user)->create(['name' => 'Ana Lopez']);

    $this->actingAs($user)
        ->patch("/prayers/{$intention->id}/archive", ['archived' => true])
        ->assertRedirect();

    expect($intention->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->get('/prayers')->assertDontSee('Ana Lopez');
    $this->actingAs($user)->get('/prayers?archived=1')->assertSee('Ana Lopez');
});

test('un archivado vuelve a la lista', function () {
    $user = User::factory()->create();
    $intention = PrayerIntention::factory()->ownedBy($user)->archived()->create([
        'name' => 'Ana Lopez',
    ]);

    $this->actingAs($user)
        ->patch("/prayers/{$intention->id}/archive", ['archived' => false])
        ->assertRedirect();

    expect($intention->fresh()->archived_at)->toBeNull();

    $this->actingAs($user)->get('/prayers')->assertSee('Ana Lopez');
});

test('un usuario no toca la lista de otro', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = PrayerIntention::factory()->ownedBy($other)->create(['name' => 'Persona ajena']);

    // Con datos válidos a propósito: si no, cortaría la validación y no se
    // vería que lo que frena es la policy.
    $this->actingAs($user)->get("/prayers/{$foreign->id}/edit")->assertForbidden();
    $this->actingAs($user)
        ->put("/prayers/{$foreign->id}", ['name' => 'Cambiada', 'custom_reason' => 'Lo que sea'])
        ->assertForbidden();
    $this->actingAs($user)
        ->patch("/prayers/{$foreign->id}/archive", ['archived' => true])
        ->assertForbidden();
    $this->actingAs($user)->delete("/prayers/{$foreign->id}")->assertForbidden();

    expect(PrayerIntention::count())->toBe(1)
        ->and($foreign->fresh()->name)->toBe('Persona ajena')
        ->and($foreign->fresh()->archived_at)->toBeNull();
});

test('se puede eliminar definitivamente la propia', function () {
    $user = User::factory()->create();
    $reason = PrayerReason::factory()->create();
    $intention = PrayerIntention::factory()->ownedBy($user)->create();
    $intention->reasons()->attach($reason);

    $this->actingAs($user)
        ->delete("/prayers/{$intention->id}")
        ->assertRedirect('/prayers');

    expect(PrayerIntention::count())->toBe(0)
        // El motivo del catálogo sigue en pie: no era suyo.
        ->and(PrayerReason::count())->toBe(1);
});

test('un invitado no llega ni a la lista', function () {
    $this->get('/prayers')->assertRedirect('/login');
});
