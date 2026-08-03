<?php

use App\Models\Mantra;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Las rachas se recalculan desde daily_aggregates cuando llegan sesiones.
 * Acá las sesiones entran por el endpoint real de sync (el mismo camino
 * que la outbox de la isla).
 */
function syncSession(User $user, Mantra $mantra, string $localDate, int $recitations = 108): void
{
    test()->actingAs($user)->postJson('/api/v1/practice-sessions', [
        'sessions' => [[
            'uuid' => (string) Str::uuid(),
            'mantra_id' => $mantra->id,
            'mode' => 'traditional',
            'recitations' => $recitations,
            'completed_malas' => intdiv($recitations, 108),
            'started_at' => "{$localDate}T10:00:00-03:00",
            'ended_at' => "{$localDate}T10:20:00-03:00",
            'duration_seconds' => 1200,
            'local_date' => $localDate,
        ]],
    ])->assertOk();
}

function localDate(User $user, int $daysAgo = 0): string
{
    return now($user->timezone)->subDays($daysAgo)->toDateString();
}

test('tres días consecutivos hasta hoy dan racha 3', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 2));
    syncSession($user, $mantra, localDate($user, 1));
    syncSession($user, $mantra, localDate($user, 0));

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(3)
        ->and($global->max_count)->toBe(3)
        ->and($global->last_local_date)->toBe(localDate($user));
});

test('un hueco resetea la racha actual pero conserva la máxima', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    // Racha vieja de 3 días (hace 10-8 días), hueco, práctica hoy
    syncSession($user, $mantra, localDate($user, 10));
    syncSession($user, $mantra, localDate($user, 9));
    syncSession($user, $mantra, localDate($user, 8));
    syncSession($user, $mantra, localDate($user, 0));

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(1)
        ->and($global->max_count)->toBe(3);
});

test('practicar ayer (todavía no hoy) mantiene la racha viva', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 2));
    syncSession($user, $mantra, localDate($user, 1));

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(2);
});

test('la última práctica hace tres días deja la racha actual en 0', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 4));
    syncSession($user, $mantra, localDate($user, 3));

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(0)
        ->and($global->max_count)->toBe(2);
});

test('una sesión offline de ayer que llega DESPUÉS de la de hoy repara la racha', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 2));
    syncSession($user, $mantra, localDate($user, 0)); // hoy: racha rota (1)

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(1);

    // Llega tarde la sesión de ayer (estaba offline en otro dispositivo)
    syncSession($user, $mantra, localDate($user, 1));

    $global->refresh();
    expect($global->current_count)->toBe(3); // recalculada, no incremental
});

test('dos sesiones el mismo día cuentan como un solo día de racha', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 0), 54);
    syncSession($user, $mantra, localDate($user, 0), 54);

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(1)
        ->and($global->max_count)->toBe(1);
});

test('la racha por mantra es independiente de la global', function () {
    $user = User::factory()->create();
    $mantraA = Mantra::factory()->create();
    $mantraB = Mantra::factory()->create();

    syncSession($user, $mantraA, localDate($user, 1));
    syncSession($user, $mantraB, localDate($user, 0));

    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    $streakA = Streak::where('user_id', $user->id)->where('mantra_id', $mantraA->id)->first();
    $streakB = Streak::where('user_id', $user->id)->where('mantra_id', $mantraB->id)->first();

    expect($global->current_count)->toBe(2) // ayer + hoy entre ambos
        ->and($streakA->current_count)->toBe(1) // ayer: sigue viva
        ->and($streakB->current_count)->toBe(1);
});

test('el bootstrap expone la racha global y los totales por mantra', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    syncSession($user, $mantra, localDate($user, 1), 108);
    syncSession($user, $mantra, localDate($user, 0), 54);

    $response = $this->actingAs($user)->getJson('/api/v1/practice/bootstrap');

    $response->assertOk()
        ->assertJsonPath('data.streak.current', 2)
        ->assertJsonPath('data.streak.max', 2)
        ->assertJsonPath('data.totals.by_mantra.'.$mantra->id, 162);
});

test('el show del mantra muestra el progreso acumulado', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();
    $user->mantras()->attach($mantra->id, ['total_goal' => 1000]);

    syncSession($user, $mantra, localDate($user, 0), 216);

    $response = $this->actingAs($user)->get("/mantras/{$mantra->id}");

    $response->assertOk();
    expect($response->viewData('page')['props']['progress']['total_recitations'])->toBe(216);
});
