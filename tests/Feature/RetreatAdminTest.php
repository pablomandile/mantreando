<?php

use App\Models\Mantra;
use App\Models\Retreat;
use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use App\Models\RetreatProgress;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function retreatAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

test('un admin crea una deidad y le carga los mantras con su cifra', function () {
    $admin = retreatAdmin();

    $this->actingAs($admin)
        ->post('/retreats/deities', ['name' => 'Guru Sumati Buda Heruka'])
        ->assertRedirect();

    $deity = RetreatDeity::first();

    expect($deity->name)->toBe('Guru Sumati Buda Heruka')
        ->and($deity->slug)->toBe('guru-sumati-buda-heruka');

    // Tres etapas con cifras distintas: la cantidad no se asume.
    foreach ([['Cien silabas', 100000], ['Mantra corto', 100000], ['Silaba HUM', 10000]] as $index => [$name, $goal]) {
        $this->actingAs($admin)
            ->post("/retreats/deities/{$deity->id}/mantras", [
                'name' => $name,
                'text' => 'OM AH HUM',
                'goal' => $goal,
                'position' => $index + 1,
            ])
            ->assertRedirect();
    }

    expect($deity->mantras()->pluck('goal')->all())->toBe([100000, 100000, 10000]);

    // Y cualquier usuario puede hacer ese retiro: ve la primera etapa.
    $user = User::factory()->create();

    $this->actingAs($user)->post('/retreats/activate', ['retreat_deity_id' => $deity->id]);

    $this->actingAs($user)
        ->get('/retreats')
        ->assertOk()
        ->assertSee('Cien silabas');
});

test('un admin corrige el nombre sin que cambie el slug', function () {
    $deity = RetreatDeity::factory()->create(['slug' => 'migtsema', 'name' => 'Migtsema']);

    $this->actingAs(retreatAdmin())
        ->from("/retreats/deities/{$deity->id}/edit")
        ->post("/retreats/deities/{$deity->id}", ['name' => 'Migtsema de nueve versos'])
        ->assertRedirect("/retreats/deities/{$deity->id}/edit");

    expect($deity->fresh()->name)->toBe('Migtsema de nueve versos')
        ->and($deity->fresh()->slug)->toBe('migtsema');
});

test('elegir una imagen ya cargada no sube nada', function () {
    Storage::fake('public');

    // La galería ofrece las láminas de los mantras del sistema.
    $mantra = Mantra::factory()->create(['image_path' => 'img/budas/heruka.jpg']);
    $deity = RetreatDeity::factory()->create();

    $this->actingAs(retreatAdmin())
        ->get("/retreats/deities/{$deity->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gallery.0.path', $mantra->image_path)
            ->etc());

    $this->actingAs(retreatAdmin())
        ->post("/retreats/deities/{$deity->id}", [
            'name' => $deity->name,
            'image_path' => 'img/budas/heruka.jpg',
        ])
        ->assertRedirect();

    expect($deity->fresh()->image_path)->toBe('img/budas/heruka.jpg');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('una ruta de imagen inventada se ignora', function () {
    $deity = RetreatDeity::factory()->create(['image_path' => null]);

    $this->actingAs(retreatAdmin())
        ->post("/retreats/deities/{$deity->id}", [
            'name' => $deity->name,
            'image_path' => '../../.env',
        ])
        ->assertRedirect();

    expect($deity->fresh()->image_path)->toBeNull();
});

test('subir una imagen nueva la guarda en el disco public', function () {
    Storage::fake('public');
    $deity = RetreatDeity::factory()->create();

    $this->actingAs(retreatAdmin())
        ->post("/retreats/deities/{$deity->id}", [
            'name' => $deity->name,
            'syllable_image' => UploadedFile::fake()->image('silaba.jpg'),
        ])
        ->assertRedirect();

    $path = $deity->fresh()->syllable_image_path;

    expect($path)->toStartWith('retreats/');
    Storage::disk('public')->assertExists($path);
});

test('un usuario comun no puede tocar el catalogo', function () {
    $deity = RetreatDeity::factory()->create();
    $stage = RetreatMantra::factory()->create(['retreat_deity_id' => $deity->id]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/retreats/deities')->assertForbidden();
    $this->actingAs($user)->post('/retreats/deities', ['name' => 'Otra'])->assertForbidden();
    $this->actingAs($user)->get("/retreats/deities/{$deity->id}/edit")->assertForbidden();
    $this->actingAs($user)->post("/retreats/deities/{$deity->id}", ['name' => 'Cambiada'])->assertForbidden();
    $this->actingAs($user)->delete("/retreats/deities/{$deity->id}")->assertForbidden();
    $this->actingAs($user)
        ->post("/retreats/deities/{$deity->id}/mantras", ['name' => 'X', 'text' => 'X', 'goal' => 10])
        ->assertForbidden();
    $this->actingAs($user)
        ->patch("/retreats/mantras/{$stage->id}", ['name' => 'X', 'text' => 'X', 'goal' => 10])
        ->assertForbidden();
    $this->actingAs($user)->delete("/retreats/mantras/{$stage->id}")->assertForbidden();

    expect(RetreatDeity::count())->toBe(1)
        ->and($deity->fresh()->name)->not->toBe('Cambiada');
});

test('la pantalla ofrece el atajo al catalogo solo a un admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/retreats')
        ->assertInertia(fn (Assert $page) => $page->where('canManageDeities', false)->etc());

    $this->actingAs(retreatAdmin())
        ->get('/retreats')
        ->assertInertia(fn (Assert $page) => $page->where('canManageDeities', true)->etc());
});

test('una deidad con retiros en curso no se elimina', function () {
    $deity = RetreatDeity::factory()->create();
    Retreat::factory()->ownedBy(User::factory()->create())->create(['retreat_deity_id' => $deity->id]);

    $this->actingAs(retreatAdmin())
        ->from('/retreats/deities')
        ->delete("/retreats/deities/{$deity->id}")
        ->assertRedirect('/retreats/deities')
        ->assertSessionHasErrors('deity');

    expect(RetreatDeity::count())->toBe(1);
});

test('un mantra con conteos no se elimina', function () {
    $deity = RetreatDeity::factory()->create();
    $stage = RetreatMantra::factory()->create(['retreat_deity_id' => $deity->id]);
    $retreat = Retreat::factory()->ownedBy(User::factory()->create())->create([
        'retreat_deity_id' => $deity->id,
    ]);
    RetreatProgress::create([
        'retreat_id' => $retreat->id,
        'retreat_mantra_id' => $stage->id,
        'count' => 5,
    ]);

    $this->actingAs(retreatAdmin())
        ->from("/retreats/deities/{$deity->id}/edit")
        ->delete("/retreats/mantras/{$stage->id}")
        ->assertRedirect("/retreats/deities/{$deity->id}/edit")
        ->assertSessionHasErrors('stage');

    expect(RetreatMantra::count())->toBe(1);
});

test('una deidad sin usar se elimina con sus mantras', function () {
    $deity = RetreatDeity::factory()->create();
    RetreatMantra::factory()->create(['retreat_deity_id' => $deity->id]);

    $this->actingAs(retreatAdmin())
        ->delete("/retreats/deities/{$deity->id}")
        ->assertRedirect('/retreats/deities');

    expect(RetreatDeity::count())->toBe(0)
        ->and(RetreatMantra::count())->toBe(0);
});
