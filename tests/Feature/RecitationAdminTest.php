<?php

use App\Enums\MantraColor;
use App\Models\Recitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Las recitaciones son las mismas para todas las cuentas: leerlas puede
 * cualquiera, mantenerlas solo un administrador.
 */
function recitationFields(array $overrides = []): array
{
    return [
        'title' => 'Los cuatro inconmensurables',
        'text' => "Que todos los seres tengan felicidad\ny las causas de la felicidad.",
        ...$overrides,
    ];
}

test('cualquiera puede leer la lista', function () {
    Recitation::create([
        'slug' => 'plegaria',
        'title' => 'Plegaria',
        'text' => 'Texto de la plegaria',
        'position' => 1,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/recitations')
        ->assertOk()
        ->assertSee('Plegaria')
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('un admin ve los controles de edición', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/recitations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

test('un admin crea una recitación y queda para todos', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/recitations', recitationFields())
        ->assertRedirect('/recitations');

    $recitation = Recitation::first();
    expect($recitation->title)->toBe('Los cuatro inconmensurables')
        ->and($recitation->slug)->toBe('los-cuatro-inconmensurables');

    // Y otra cuenta la ve, sin nada que compartir explícitamente.
    $this->actingAs(User::factory()->create())
        ->get('/recitations')
        ->assertSee('Los cuatro inconmensurables');
});

test('el texto conserva los saltos de línea', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/recitations', recitationFields());

    expect(Recitation::first()->text)->toContain("felicidad\ny las causas");
});

test('dos recitaciones con el mismo título no chocan de slug', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post('/recitations', recitationFields());
    $this->actingAs($admin)->post('/recitations', recitationFields());

    expect(Recitation::pluck('slug')->all())
        ->toBe(['los-cuatro-inconmensurables', 'los-cuatro-inconmensurables-2']);
});

test('la nueva queda al final del orden', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Recitation::create(['slug' => 'a', 'title' => 'A', 'text' => 'x', 'position' => 7]);

    $this->actingAs($admin)->post('/recitations', recitationFields());

    expect(Recitation::where('slug', 'los-cuatro-inconmensurables')->first()->position)->toBe(8);
});

test('la nueva recitación viene con color', function () {
    // Sin color la tarjeta queda gris entre las del seeder, que sí lo traen.
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/recitations', recitationFields());

    expect(Recitation::first()->color)->not->toBeNull()
        ->and(Recitation::first()->color)->toBeInstanceOf(MantraColor::class);
});

test('el color no repite el de las tarjetas vecinas', function () {
    // mt_srand fija el azar: el test es reproducible, no sale distinto cada vez.
    mt_srand(42);
    $admin = User::factory()->create(['is_admin' => true]);
    Recitation::create(['slug' => 'antes', 'title' => 'Antes', 'text' => 'x', 'position' => 5, 'color' => 'amber']);
    Recitation::create(['slug' => 'despues', 'title' => 'Despues', 'text' => 'x', 'position' => 7, 'color' => 'green']);

    $this->actingAs($admin)->post('/recitations', recitationFields(['position' => 6]));

    expect(Recitation::where('slug', 'los-cuatro-inconmensurables')->first()->color->value)
        ->not->toBe('amber')
        ->not->toBe('green');
});

test('ninguna de una tanda de altas repite el color de la anterior', function () {
    mt_srand(1);
    $admin = User::factory()->create(['is_admin' => true]);

    foreach (range(1, 15) as $i) {
        $this->actingAs($admin)->post('/recitations', recitationFields(['title' => "Recitación {$i}"]));
    }

    $colors = Recitation::orderBy('position')->pluck('color')->all();
    expect($colors)->toHaveCount(15);

    foreach (array_slice($colors, 1) as $index => $color) {
        expect($color->value)->not->toBe($colors[$index]->value);
    }
});

test('un admin edita el título sin que cambie el slug', function () {
    // El slug es la identidad estable que usa el seeder para no duplicar filas.
    $recitation = Recitation::create([
        'slug' => 'plegaria',
        'title' => 'Plegaria',
        'text' => 'Texto',
        'position' => 1,
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->put("/recitations/{$recitation->id}", recitationFields(['title' => 'Plegaria corregida']))
        ->assertRedirect('/recitations');

    expect($recitation->fresh()->title)->toBe('Plegaria corregida')
        ->and($recitation->fresh()->slug)->toBe('plegaria');
});

test('un admin elimina una recitación', function () {
    $recitation = Recitation::create([
        'slug' => 'plegaria',
        'title' => 'Plegaria',
        'text' => 'Texto',
        'position' => 1,
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->delete("/recitations/{$recitation->id}")
        ->assertRedirect('/recitations');

    expect(Recitation::count())->toBe(0);
});

test('un usuario común no puede tocar las recitaciones', function () {
    $recitation = Recitation::create([
        'slug' => 'plegaria',
        'title' => 'Plegaria',
        'text' => 'Texto',
        'position' => 1,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/recitations/create')->assertForbidden();
    $this->actingAs($user)->post('/recitations', recitationFields())->assertForbidden();
    $this->actingAs($user)->get("/recitations/{$recitation->id}/edit")->assertForbidden();
    $this->actingAs($user)->put("/recitations/{$recitation->id}", recitationFields())->assertForbidden();
    $this->actingAs($user)->delete("/recitations/{$recitation->id}")->assertForbidden();

    expect(Recitation::count())->toBe(1)
        ->and($recitation->fresh()->title)->toBe('Plegaria');
});

test('un invitado no llega ni a la lista', function () {
    $this->get('/recitations')->assertRedirect('/login');
});

test('el título es obligatorio', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/recitations', recitationFields(['title' => '']))
        ->assertSessionHasErrors('title');
});
