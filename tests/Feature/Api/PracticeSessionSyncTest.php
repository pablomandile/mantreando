<?php

use App\Models\DailyAggregate;
use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Support\Str;

function sessionPayload(Mantra $mantra, array $overrides = []): array
{
    return [
        'uuid' => (string) Str::uuid(),
        'mantra_id' => $mantra->id,
        'mode' => 'traditional',
        'recitations' => 108,
        'completed_malas' => 1,
        'started_at' => '2026-08-02T21:00:00-03:00',
        'ended_at' => '2026-08-02T21:15:00-03:00',
        'duration_seconds' => 900,
        'local_date' => '2026-08-02',
        ...$overrides,
    ];
}

test('un guest recibe 401', function () {
    $this->postJson('/api/v1/practice-sessions', ['sessions' => []])
        ->assertUnauthorized();
});

test('un batch válido crea sesiones y agregados por mantra y por día', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create(); // del sistema

    $payload = sessionPayload($mantra);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.results.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.results.0.status', 'created');

    $session = PracticeSession::where('uuid', $payload['uuid'])->first();
    expect($session)->not->toBeNull()
        ->and($session->user_id)->toBe($user->id)
        ->and($session->recitations)->toBe(108)
        ->and($session->local_date)->toBe('2026-08-02')
        ->and($session->synced_at)->not->toBeNull();

    // Agregado por mantra
    $byMantra = DailyAggregate::where('user_id', $user->id)
        ->where('mantra_id', $mantra->id)
        ->where('local_date', '2026-08-02')
        ->first();
    expect($byMantra->recitations)->toBe(108)
        ->and($byMantra->malas)->toBe(1)
        ->and($byMantra->duration_seconds)->toBe(900)
        ->and($byMantra->sessions_count)->toBe(1);

    // Agregado total del día (mantra_id null)
    $dayTotal = DailyAggregate::where('user_id', $user->id)
        ->whereNull('mantra_id')
        ->where('local_date', '2026-08-02')
        ->first();
    expect($dayTotal->recitations)->toBe(108)
        ->and($dayTotal->sessions_count)->toBe(1);
});

test('re-postear el mismo batch devuelve duplicate sin cambiar nada', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();
    $payload = sessionPayload($mantra);

    $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$payload]])
        ->assertOk();

    // Segundo intento idéntico (reintento de outbox)
    $response = $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'duplicate');

    expect(PracticeSession::count())->toBe(1);

    $dayTotal = DailyAggregate::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($dayTotal->recitations)->toBe(108)
        ->and($dayTotal->sessions_count)->toBe(1);
});

test('un batch mixto reporta el inválido y crea el válido', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $valid = sessionPayload($mantra);
    $invalid = sessionPayload($mantra, ['uuid' => 'no-es-un-uuid', 'recitations' => -5]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$invalid, $valid]]);

    $response->assertOk();

    $results = collect($response->json('data.results'));
    expect($results->firstWhere('status', 'invalid'))->not->toBeNull()
        ->and($results->firstWhere('status', 'created')['uuid'])->toBe($valid['uuid'])
        ->and(PracticeSession::count())->toBe(1);
});

test('el mantra de otro usuario se rechaza por ítem', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Mantra::factory()->ownedBy($other)->create();

    $payload = sessionPayload($foreign);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$payload]]);

    $response->assertOk()
        ->assertJsonPath('data.results.0.status', 'invalid');

    expect(PracticeSession::count())->toBe(0);
});

test('el mantra propio del usuario sí se acepta', function () {
    $user = User::factory()->create();
    $own = Mantra::factory()->ownedBy($user)->create();

    $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [sessionPayload($own)]])
        ->assertOk()
        ->assertJsonPath('data.results.0.status', 'created');
});

test('un batch vacío o de más de 50 se rechaza completo', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => []])
        ->assertUnprocessable();

    $tooMany = array_map(
        fn () => sessionPayload($mantra),
        range(1, 51),
    );

    $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => $tooMany])
        ->assertUnprocessable();
});

test('dos sesiones del mismo día acumulan en el mismo agregado', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $first = sessionPayload($mantra);
    $second = sessionPayload($mantra, ['recitations' => 54, 'completed_malas' => 0, 'duration_seconds' => 450]);

    $this->actingAs($user)
        ->postJson('/api/v1/practice-sessions', ['sessions' => [$first, $second]])
        ->assertOk();

    $dayTotal = DailyAggregate::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($dayTotal->recitations)->toBe(162)
        ->and($dayTotal->malas)->toBe(1)
        ->and($dayTotal->duration_seconds)->toBe(1350)
        ->and($dayTotal->sessions_count)->toBe(2);
});
