<?php

use App\Models\DailyAggregate;
use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\Streak;
use App\Models\User;

function seedDay(User $user, Mantra $mantra, string $date, int $recitations): void
{
    PracticeSession::factory()->create([
        'user_id' => $user->id,
        'mantra_id' => $mantra->id,
        'local_date' => $date,
        'recitations' => $recitations,
    ]);

    DailyAggregate::create([
        'user_id' => $user->id,
        'mantra_id' => $mantra->id,
        'local_date' => $date,
        'recitations' => $recitations,
        'sessions_count' => 1,
    ]);

    // El total del día es una sola fila (unique por user+mantra_key+fecha):
    // si ya hay práctica de otro mantra ese día, acumula.
    $dayTotal = DailyAggregate::firstOrCreate([
        'user_id' => $user->id,
        'mantra_id' => null,
        'local_date' => $date,
    ]);

    $dayTotal->increment('recitations', $recitations);
    $dayTotal->increment('sessions_count');
}

test('un invitado no puede reiniciar el dia', function () {
    $this->deleteJson('/api/v1/practice/today', ['local_date' => '2026-08-04'])
        ->assertUnauthorized();
});

test('reiniciar borra las sesiones y los agregados de ese dia', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();

    seedDay($user, $mantra, $hoy, 54);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', ['local_date' => $hoy])
        ->assertOk();

    expect(PracticeSession::where('user_id', $user->id)->count())->toBe(0)
        ->and(DailyAggregate::where('user_id', $user->id)->count())->toBe(0);
});

test('el historial de otros dias queda intacto', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();
    $viejo = now($user->timezone)->subDays(5)->toDateString();

    seedDay($user, $mantra, $viejo, 108);
    seedDay($user, $mantra, $hoy, 21);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', ['local_date' => $hoy])
        ->assertOk();

    expect(PracticeSession::where('local_date', $viejo)->count())->toBe(1)
        ->and(DailyAggregate::where('local_date', $viejo)->count())->toBe(2)
        ->and(DailyAggregate::where('local_date', $hoy)->count())->toBe(0);
});

test('no se puede borrar un dia viejo aunque el cliente lo pida', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $viejo = now($user->timezone)->subDays(5)->toDateString();

    seedDay($user, $mantra, $viejo, 108);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', ['local_date' => $viejo])
        ->assertStatus(422);

    expect(PracticeSession::where('local_date', $viejo)->count())->toBe(1);
});

test('la practica de otro usuario no se toca', function () {
    $ajeno = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();

    seedDay($ajeno, $mantra, $hoy, 108);
    seedDay($user, $mantra, $hoy, 21);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', ['local_date' => $hoy])
        ->assertOk();

    expect(PracticeSession::where('user_id', $ajeno->id)->count())->toBe(1)
        ->and(DailyAggregate::where('user_id', $ajeno->id)->count())->toBe(2);
});

test('la racha se recalcula tras reiniciar', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();
    $ayer = now($user->timezone)->subDay()->toDateString();

    seedDay($user, $mantra, $ayer, 108);
    seedDay($user, $mantra, $hoy, 108);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', ['local_date' => $hoy])
        ->assertOk();

    // Queda solo ayer: la racha global vuelve a 1 (sigue vigente porque
    // ayer cuenta), no 2.
    $global = Streak::where('user_id', $user->id)->whereNull('mantra_id')->first();
    expect($global->current_count)->toBe(1);
});

test('con mantra_id solo borra ese mantra y deja los demas del dia', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $tara = Mantra::factory()->create();
    $manjushri = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();

    seedDay($user, $tara, $hoy, 21);
    seedDay($user, $manjushri, $hoy, 54);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', [
            'local_date' => $hoy,
            'mantra_id' => $tara->id,
        ])
        ->assertOk();

    // Tara borrado, Manjushri intacto
    expect(PracticeSession::where('mantra_id', $tara->id)->count())->toBe(0)
        ->and(PracticeSession::where('mantra_id', $manjushri->id)->count())->toBe(1)
        ->and(DailyAggregate::where('mantra_id', $tara->id)->count())->toBe(0)
        ->and(DailyAggregate::where('mantra_id', $manjushri->id)->count())->toBe(1);

    // El total del día se rehace con lo que quedó, no se borra
    $dayTotal = DailyAggregate::where('user_id', $user->id)
        ->whereNull('mantra_id')
        ->where('local_date', $hoy)
        ->first();

    expect($dayTotal)->not->toBeNull()
        ->and($dayTotal->recitations)->toBe(54);
});

test('borrar el ultimo mantra del dia deja el total del dia en nada', function () {
    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $mantra = Mantra::factory()->create();
    $hoy = now($user->timezone)->toDateString();

    seedDay($user, $mantra, $hoy, 21);

    $this->actingAs($user)
        ->deleteJson('/api/v1/practice/today', [
            'local_date' => $hoy,
            'mantra_id' => $mantra->id,
        ])
        ->assertOk();

    expect(DailyAggregate::where('user_id', $user->id)->count())->toBe(0);
});

test('la fecha es obligatoria y con formato', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->deleteJson('/api/v1/practice/today', [])
        ->assertStatus(422);

    $this->actingAs($user)->deleteJson('/api/v1/practice/today', ['local_date' => '04-08-2026'])
        ->assertStatus(422);
});
