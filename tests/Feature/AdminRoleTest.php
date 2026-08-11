<?php

use App\Models\Mantra;
use App\Models\MantraCategory;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * El rol de administrador: publicar mantras para todos y mantener las otras
 * recitaciones. Lo que importa acá no es que el admin pueda, sino que el resto
 * NO pueda aunque mande el campo a mano.
 */
function adminUser(): User
{
    return User::factory()->create(['is_admin' => true]);
}

/** @return array<string, mixed> */
function mantraFields(array $overrides = []): array
{
    return [
        'name' => 'Mantra de prueba',
        'text' => 'om ah hum',
        'category_id' => MantraCategory::factory()->create()->id,
        ...$overrides,
    ];
}

test('el comando da y quita el rol', function () {
    $user = User::factory()->create(['email' => 'pablo@ejemplo.com']);

    $this->artisan('user:admin pablo@ejemplo.com')->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeTrue();

    $this->artisan('user:admin pablo@ejemplo.com --revoke')->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeFalse();
});

test('el comando falla con un mail que no existe', function () {
    $this->artisan('user:admin nadie@ejemplo.com')->assertFailed();
});

test('el rol no se puede asignar por mass assignment', function () {
    // La columna queda fuera de Fillable a propósito: si entrara por acá,
    // cualquier fill() con datos del request sería una puerta al rol.
    // fresh() porque el default lo pone la base: el modelo recién creado
    // todavía no tiene el atributo cargado y daría null.
    $user = User::factory()->create()->fresh();

    $user->fill(['is_admin' => true]);

    expect($user->is_admin)->toBeFalse()
        ->and($user->isAdmin())->toBeFalse();
});

test('un admin publica un mantra para todos', function () {
    $this->actingAs(adminUser())
        ->post('/mantras', mantraFields(['is_shared' => true]))
        ->assertRedirect();

    $mantra = Mantra::where('name', 'Mantra de prueba')->first();
    expect($mantra->user_id)->toBeNull()
        ->and($mantra->isSystem())->toBeTrue();
});

test('un admin también puede crear un mantra solo para sí', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post('/mantras', mantraFields());

    expect(Mantra::where('name', 'Mantra de prueba')->first()->user_id)->toBe($admin->id);
});

test('un usuario común no puede publicar aunque mande el campo', function () {
    $user = User::factory()->create();

    // No hay UI que mande esto: es el caso del formulario manipulado.
    $this->actingAs($user)
        ->post('/mantras', mantraFields(['is_shared' => true]))
        ->assertRedirect();

    expect(Mantra::where('name', 'Mantra de prueba')->first()->user_id)->toBe($user->id);
});

test('el mantra publicado lo ve otra cuenta en la biblioteca', function () {
    $this->actingAs(adminUser())->post('/mantras', mantraFields(['is_shared' => true]));

    $this->actingAs(User::factory()->create())
        ->get('/mantras')
        ->assertOk()
        ->assertSee('Mantra de prueba');
});

test('el mantra publicado viaja al bootstrap de la isla de otra cuenta', function () {
    // La práctica offline lee de acá: si no llega al bootstrap, el mantra no
    // existe para el dispositivo.
    $this->actingAs(adminUser())->post('/mantras', mantraFields(['is_shared' => true]));

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/practice/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.mantras.0.name', 'Mantra de prueba');
});

test('un mantra propio del admin NO le aparece a otra cuenta', function () {
    $this->actingAs(adminUser())->post('/mantras', mantraFields(['name' => 'Solo mío']));

    $this->actingAs(User::factory()->create())
        ->get('/mantras')
        ->assertOk()
        ->assertDontSee('Solo mío');
});

test('un admin edita un mantra del sistema', function () {
    $mantra = Mantra::factory()->create(['user_id' => null, 'name' => 'Original']);

    $this->actingAs(adminUser())
        ->put("/mantras/{$mantra->id}", mantraFields([
            'name' => 'Corregido',
            'is_shared' => true,
        ]))
        ->assertRedirect();

    expect($mantra->fresh()->name)->toBe('Corregido');
});

test('un usuario común no puede editar un mantra del sistema', function () {
    $mantra = Mantra::factory()->create(['user_id' => null, 'name' => 'Original']);

    $this->actingAs(User::factory()->create())
        ->put("/mantras/{$mantra->id}", mantraFields(['name' => 'Pisado']))
        ->assertForbidden();

    expect($mantra->fresh()->name)->toBe('Original');
});

test('un admin puede despublicar un mantra que nadie más usa', function () {
    $admin = adminUser();
    $mantra = Mantra::factory()->create(['user_id' => null]);

    $this->actingAs($admin)
        ->put("/mantras/{$mantra->id}", mantraFields(['is_shared' => false]))
        ->assertRedirect();

    expect($mantra->fresh()->user_id)->toBe($admin->id);
});

test('no se puede despublicar un mantra que otra cuenta ya practica', function () {
    // Despublicar dejaría a esa cuenta sin acceso a un mantra que ya practicó,
    // con sus sesiones apuntando a algo que no puede ver.
    $mantra = Mantra::factory()->create(['user_id' => null]);
    PracticeSession::factory()->create([
        'user_id' => User::factory()->create()->id,
        'mantra_id' => $mantra->id,
    ]);

    $this->actingAs(adminUser())
        ->put("/mantras/{$mantra->id}", mantraFields(['is_shared' => false]))
        ->assertSessionHasErrors('is_shared');

    expect($mantra->fresh()->user_id)->toBeNull();
});

test('no se puede despublicar un mantra que otra cuenta tiene en favoritos', function () {
    $mantra = Mantra::factory()->create(['user_id' => null]);
    DB::table('mantra_user')->insert([
        'user_id' => User::factory()->create()->id,
        'mantra_id' => $mantra->id,
        'is_favorite' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(adminUser())
        ->put("/mantras/{$mantra->id}", mantraFields(['is_shared' => false]))
        ->assertSessionHasErrors('is_shared');

    expect($mantra->fresh()->user_id)->toBeNull();
});

test('el formulario ofrece publicar solo a los administradores', function () {
    $this->actingAs(adminUser())
        ->get('/mantras/create')
        ->assertInertia(fn (Assert $page) => $page->where('canShare', true));

    $this->actingAs(User::factory()->create())
        ->get('/mantras/create')
        ->assertInertia(fn (Assert $page) => $page->where('canShare', false));
});

test('la edición informa si el mantra ya es del sistema', function () {
    $mantra = Mantra::factory()->create(['user_id' => null]);

    $this->actingAs(adminUser())
        ->get("/mantras/{$mantra->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->where('canShare', true)
            ->where('isShared', true)
        );
});
