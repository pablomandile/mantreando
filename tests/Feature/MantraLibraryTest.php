<?php

use App\Models\Mantra;
use App\Models\MantraCategory;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('el índice lista mantras del sistema y propios, nunca ajenos', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $system = Mantra::factory()->create(['name' => 'Om Mani Padme Hum']);
    $own = Mantra::factory()->ownedBy($user)->create(['name' => 'Mi mantra personal']);
    Mantra::factory()->ownedBy($other)->create(['name' => 'Mantra ajeno']);

    $response = $this->actingAs($user)->get('/mantras');

    $response->assertOk()
        ->assertSee('Om Mani Padme Hum')
        ->assertSee('Mi mantra personal')
        ->assertDontSee('Mantra ajeno');
});

test('la búsqueda filtra por nombre, texto y transliteración', function () {
    $user = User::factory()->create();
    Mantra::factory()->create(['name' => 'Tara Verde', 'text' => 'Om Tare']);
    Mantra::factory()->create(['name' => 'Buda Medicina', 'text' => 'Tayata Om']);

    $response = $this->actingAs($user)->get('/mantras?q=Tara');

    $response->assertOk()->assertSee('Tara Verde')->assertDontSee('Buda Medicina');
});

test('el filtro por categoría funciona por slug', function () {
    $user = User::factory()->create();
    $wisdom = MantraCategory::factory()->create(['slug' => 'wisdom-x']);
    $healing = MantraCategory::factory()->create(['slug' => 'healing-x']);
    // Sin acentos: los props de Inertia viajan JSON-escapados y assertSee no los matchea.
    Mantra::factory()->create(['name' => 'Mantra wisdom', 'category_id' => $wisdom->id]);
    Mantra::factory()->create(['name' => 'Mantra healing', 'category_id' => $healing->id]);

    $this->actingAs($user)->get('/mantras?category=wisdom-x')
        ->assertSee('Mantra wisdom')
        ->assertDontSee('Mantra healing');
});

test('crear un mantra propio con imagen', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $category = MantraCategory::factory()->create();

    $response = $this->actingAs($user)->post('/mantras', [
        'name' => 'Mantra de Manjushri',
        'text' => 'Om Ah Ra Pa Tsa Na Dhi',
        'category_id' => $category->id,
        'image' => UploadedFile::fake()->image('manjushri.jpg', 600, 600),
    ]);

    $mantra = Mantra::where('name', 'Mantra de Manjushri')->first();
    expect($mantra)->not->toBeNull()
        ->and($mantra->user_id)->toBe($user->id)
        ->and($mantra->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($mantra->image_path);
    $response->assertRedirect(route('mantras.show', $mantra));
});

test('la validación exige nombre, texto y categoría', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/mantras', [])
        ->assertSessionHasErrors(['name', 'text', 'category_id']);
});

test('editar un mantra propio', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->ownedBy($user)->create();

    $this->actingAs($user)->put("/mantras/{$mantra->id}", [
        'name' => 'Nombre nuevo',
        'text' => $mantra->text,
        'category_id' => $mantra->category_id,
    ])->assertRedirect(route('mantras.show', $mantra));

    expect($mantra->refresh()->name)->toBe('Nombre nuevo');
});

test('no se puede editar un mantra del sistema ni uno ajeno', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $system = Mantra::factory()->create();
    $foreign = Mantra::factory()->ownedBy($other)->create();

    $payload = ['name' => 'Hack', 'text' => 'x', 'category_id' => $system->category_id];

    $this->actingAs($user)->put("/mantras/{$system->id}", $payload)->assertForbidden();
    $this->actingAs($user)->put("/mantras/{$foreign->id}", $payload)->assertForbidden();
    $this->actingAs($user)->get("/mantras/{$system->id}/edit")->assertForbidden();
});

test('reemplazar la imagen borra la anterior', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $mantra = Mantra::factory()->ownedBy($user)->create();

    $this->actingAs($user)->put("/mantras/{$mantra->id}", [
        'name' => $mantra->name,
        'text' => $mantra->text,
        'category_id' => $mantra->category_id,
        'image' => UploadedFile::fake()->image('a.jpg'),
    ]);
    $firstPath = $mantra->refresh()->image_path;

    $this->actingAs($user)->put("/mantras/{$mantra->id}", [
        'name' => $mantra->name,
        'text' => $mantra->text,
        'category_id' => $mantra->category_id,
        'image' => UploadedFile::fake()->image('b.jpg'),
    ]);

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($mantra->refresh()->image_path);
});

test('eliminar un mantra propio sin sesiones', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->ownedBy($user)->create();

    $this->actingAs($user)->delete("/mantras/{$mantra->id}")
        ->assertRedirect(route('mantras.index'));

    expect(Mantra::find($mantra->id))->toBeNull();
});

test('un mantra con sesiones de práctica no puede eliminarse', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->ownedBy($user)->create();
    PracticeSession::factory()->create(['user_id' => $user->id, 'mantra_id' => $mantra->id]);

    $this->actingAs($user)->delete("/mantras/{$mantra->id}")
        ->assertSessionHasErrors('mantra');

    expect(Mantra::find($mantra->id))->not->toBeNull();
});

test('el toggle de favorito crea y alterna la fila pivot', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create(); // del sistema: también favoriteable

    $this->actingAs($user)->post("/mantras/{$mantra->id}/favorite");
    expect((bool) $user->mantras()->find($mantra->id)->pivot->is_favorite)->toBeTrue();

    $this->actingAs($user)->post("/mantras/{$mantra->id}/favorite");
    expect((bool) $user->mantras()->find($mantra->id)->pivot->is_favorite)->toBeFalse();
});

test('compromiso diario y objetivo total se guardan en la pivot', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $this->actingAs($user)->patch("/mantras/{$mantra->id}/practice-settings", [
        'daily_commitment' => 108,
        'total_goal' => 100000,
    ])->assertRedirect();

    $pivot = $user->mantras()->find($mantra->id)->pivot;
    expect($pivot->daily_commitment)->toBe(108)
        ->and($pivot->total_goal)->toBe(100000);
});

test('los ajustes de práctica validan enteros positivos', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $this->actingAs($user)->patch("/mantras/{$mantra->id}/practice-settings", [
        'daily_commitment' => -5,
    ])->assertSessionHasErrors('daily_commitment');
});

test('el show de un mantra ajeno devuelve 403', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Mantra::factory()->ownedBy($other)->create();

    $this->actingAs($user)->get("/mantras/{$foreign->id}")->assertForbidden();
});
