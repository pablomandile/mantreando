<?php

use App\Models\Retreat;
use App\Models\RetreatDeity;
use App\Models\RetreatMantra;
use App\Models\RetreatProgress;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Sin acentos en lo que se busca con assertSee: los props de Inertia viajan
// JSON-escapados y no matchean.

/** Una deidad con sus etapas, como la carga el admin. */
function deityWithStages(string $name = 'Vajrasattva', array $goals = [100000, 10000]): RetreatDeity
{
    $deity = RetreatDeity::factory()->create([
        'name' => $name,
        'slug' => strtolower($name),
    ]);

    foreach ($goals as $index => $goal) {
        RetreatMantra::factory()->create([
            'retreat_deity_id' => $deity->id,
            'position' => $index + 1,
            'name' => 'Etapa '.($index + 1),
            'goal' => $goal,
        ]);
    }

    return $deity->fresh();
}

test('sin retiro activo la pantalla ofrece elegir deidad', function () {
    deityWithStages();

    $this->actingAs(User::factory()->create())
        ->get('/retreats')
        ->assertOk()
        ->assertSee('Vajrasattva')
        ->assertInertia(fn (Assert $page) => $page
            ->where('retreat', null)
            ->where('canManageDeities', false)
            ->etc());
});

test('activar una deidad abre el retiro con su fecha de inicio', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/retreats/activate', [
            'retreat_deity_id' => $deity->id,
            'local_date' => '2026-08-30',
        ])
        ->assertRedirect();

    $retreat = Retreat::first();

    expect($retreat->user_id)->toBe($user->id)
        ->and($retreat->retreat_deity_id)->toBe($deity->id)
        ->and($retreat->started_on->toDateString())->toBe('2026-08-30')
        ->and($retreat->completed_on)->toBeNull();
});

test('la etapa en curso es la primera sin cerrar', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);

    $first = $deity->mantras()->first();

    $this->actingAs($user)
        ->get('/retreats')
        ->assertInertia(fn (Assert $page) => $page
            ->where('retreat.current_stage_id', $first->id)
            ->count('retreat.stages', 2)
            ->etc());
});

test('el conteo se guarda con el valor absoluto y no se duplica', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);
    $stage = $deity->mantras()->first();

    foreach ([37, 37, 120] as $count) {
        $this->actingAs($user)
            ->patch("/retreats/{$retreat->id}/count", [
                'retreat_mantra_id' => $stage->id,
                'count' => $count,
            ])
            ->assertRedirect();
    }

    expect(RetreatProgress::count())->toBe(1)
        ->and(RetreatProgress::first()->count)->toBe(120);
});

test('se puede contar por encima de la cifra', function () {
    $deity = deityWithStages('Migtsema', [100]);
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);
    $stage = $deity->mantras()->first();

    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}/count", [
            'retreat_mantra_id' => $stage->id,
            'count' => 240,
        ])
        ->assertRedirect();

    expect(RetreatProgress::first()->count)->toBe(240)
        // La etapa sigue abierta: la cierra el usuario, no la cifra.
        ->and(RetreatProgress::first()->completed_on)->toBeNull();
});

test('una etapa de otra deidad no entra en este retiro', function () {
    $deity = deityWithStages();
    $ajena = deityWithStages('Heruka', [100]);
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);

    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}/count", [
            'retreat_mantra_id' => $ajena->mantras()->first()->id,
            'count' => 10,
        ])
        ->assertSessionHasErrors('retreat_mantra_id');

    expect(RetreatProgress::count())->toBe(0);
});

test('cerrar una etapa deja la siguiente en curso y la ultima completa el retiro', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);
    [$first, $second] = $deity->mantras()->get()->all();

    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}/stage", [
            'retreat_mantra_id' => $first->id,
            'completed' => true,
            'local_date' => '2026-09-01',
        ])
        ->assertRedirect();

    expect($retreat->fresh()->currentStage()->id)->toBe($second->id)
        ->and($retreat->fresh()->completed_on)->toBeNull();

    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}/stage", [
            'retreat_mantra_id' => $second->id,
            'completed' => true,
            'local_date' => '2026-09-02',
        ])
        ->assertRedirect();

    expect($retreat->fresh()->currentStage())->toBeNull()
        ->and($retreat->fresh()->completed_on->toDateString())->toBe('2026-09-02');
});

test('cambiar de deidad no pierde lo contado', function () {
    $primera = deityWithStages('Vajrasattva', [100]);
    $segunda = deityWithStages('Heruka', [100]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/retreats/activate', ['retreat_deity_id' => $primera->id]);
    $retreat = Retreat::first();

    $this->actingAs($user)->patch("/retreats/{$retreat->id}/count", [
        'retreat_mantra_id' => $primera->mantras()->first()->id,
        'count' => 42,
    ]);

    // Se pasa a la otra y se vuelve.
    $this->actingAs($user)->post('/retreats/activate', ['retreat_deity_id' => $segunda->id]);

    $this->actingAs($user)
        ->get('/retreats')
        ->assertInertia(fn (Assert $page) => $page
            ->where('retreat.deity.name', 'Heruka')
            ->etc());

    $this->actingAs($user)->post('/retreats/activate', ['retreat_deity_id' => $primera->id]);

    $this->actingAs($user)
        ->get('/retreats')
        ->assertInertia(fn (Assert $page) => $page
            ->where('retreat.deity.name', 'Vajrasattva')
            ->where('retreat.stages.0.count', 42)
            ->etc());

    expect(Retreat::count())->toBe(2);
});

test('un usuario no toca el retiro de otro', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $ajeno = Retreat::factory()->ownedBy($other)->create(['retreat_deity_id' => $deity->id]);
    $stage = $deity->mantras()->first();

    $this->actingAs($user)
        ->patch("/retreats/{$ajeno->id}/count", [
            'retreat_mantra_id' => $stage->id,
            'count' => 500,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch("/retreats/{$ajeno->id}/stage", [
            'retreat_mantra_id' => $stage->id,
            'completed' => true,
        ])
        ->assertForbidden();

    expect(RetreatProgress::count())->toBe(0);
});

test('las notas y la dedicacion se guardan por separado', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);

    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}", ['notes' => 'Hoy costo mas sostener la postura.'])
        ->assertRedirect();

    // Guardar solo la dedicacion no pisa las notas: 'sometimes' hace que
    // validate() devuelva unicamente lo que vino en el request.
    $this->actingAs($user)
        ->patch("/retreats/{$retreat->id}", ['dedications' => 'Que todos los seres tengan felicidad.'])
        ->assertRedirect();

    expect($retreat->fresh()->notes)->toBe('Hoy costo mas sostener la postura.')
        ->and($retreat->fresh()->dedications)->toBe('Que todos los seres tengan felicidad.');
});

test('reiniciar el conteo pide el nombre exacto de la deidad', function () {
    $deity = deityWithStages('Vajrasattva', [100000, 10000]);
    $user = User::factory()->create();
    $retreat = Retreat::factory()->ownedBy($user)->create(['retreat_deity_id' => $deity->id]);
    [$first, $second] = $deity->mantras()->get()->all();

    RetreatProgress::factory()->create([
        'retreat_id' => $retreat->id,
        'retreat_mantra_id' => $first->id,
        'count' => 100000,
        'completed_on' => '2026-09-01',
    ]);
    RetreatProgress::factory()->create([
        'retreat_id' => $retreat->id,
        'retreat_mantra_id' => $second->id,
        'count' => 4200,
    ]);
    $retreat->update(['completed_on' => '2026-09-01']);

    // El nombre equivocado no reinicia nada.
    $this->actingAs($user)
        ->post("/retreats/{$retreat->id}/reset", ['confirm_name' => 'Heruka'])
        ->assertSessionHasErrors('confirm_name');

    expect(RetreatProgress::sum('count'))->toBe(104200);

    // El nombre correcto (sin importar mayusculas ni espacios) reinicia todo.
    $this->actingAs($user)
        ->post("/retreats/{$retreat->id}/reset", ['confirm_name' => ' vajrasattva '])
        ->assertRedirect();

    expect(RetreatProgress::sum('count'))->toBe(0)
        ->and(RetreatProgress::whereNotNull('completed_on')->count())->toBe(0)
        ->and($retreat->fresh()->completed_on)->toBeNull();
});

test('un usuario no reinicia ni edita el retiro de otro', function () {
    $deity = deityWithStages();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $ajeno = Retreat::factory()->ownedBy($other)->create(['retreat_deity_id' => $deity->id]);

    $this->actingAs($user)
        ->post("/retreats/{$ajeno->id}/reset", ['confirm_name' => $deity->name])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch("/retreats/{$ajeno->id}", ['notes' => 'ajeno'])
        ->assertForbidden();

    expect($ajeno->fresh()->notes)->toBeNull();
});

test('un invitado no llega al retiro', function () {
    $this->get('/retreats')->assertRedirect('/login');
});
