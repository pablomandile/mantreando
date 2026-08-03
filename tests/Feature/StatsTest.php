<?php

use App\Models\Mantra;
use App\Models\User;
use Illuminate\Support\Str;

function statsSession(User $user, Mantra $mantra, string $localDate, int $recitations, int $seconds = 600): void
{
    test()->actingAs($user)->postJson('/api/v1/practice-sessions', [
        'sessions' => [[
            'uuid' => (string) Str::uuid(),
            'mantra_id' => $mantra->id,
            'mode' => 'assisted',
            'recitations' => $recitations,
            'completed_malas' => intdiv($recitations, 108),
            'started_at' => "{$localDate}T09:00:00-03:00",
            'ended_at' => "{$localDate}T09:30:00-03:00",
            'duration_seconds' => $seconds,
            'local_date' => $localDate,
        ]],
    ])->assertOk();
}

function props($response): array
{
    return $response->viewData('page')['props'];
}

test('la semana devuelve 7 buckets diarios con huecos en cero', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $today = now($user->timezone)->toDateString();
    statsSession($user, $mantra, $today, 108, 900);

    $response = $this->actingAs($user)->get('/stats?range=week');

    $response->assertOk();
    $data = props($response);

    expect($data['series'])->toHaveCount(7)
        ->and(end($data['series'])['value'])->toBe(108)
        ->and($data['series'][0]['value'])->toBe(0)
        ->and($data['granularity'])->toBe('day')
        ->and($data['totals']['recitations'])->toBe(108)
        ->and($data['totals']['duration_seconds'])->toBe(900);
});

test('el año devuelve 12 buckets mensuales agregados', function () {
    $user = User::factory()->create();
    $mantra = Mantra::factory()->create();

    $today = now($user->timezone);
    statsSession($user, $mantra, $today->toDateString(), 108);
    statsSession($user, $mantra, $today->copy()->subDays(1)->toDateString(), 54);

    $response = $this->actingAs($user)->get('/stats?range=year');
    $data = props($response);

    expect($data['series'])->toHaveCount(12)
        ->and($data['granularity'])->toBe('month')
        ->and(collect($data['series'])->sum('value'))->toBe(162);
});

test('el filtro por mantra restringe la serie y los totales', function () {
    $user = User::factory()->create();
    $mantraA = Mantra::factory()->create();
    $mantraB = Mantra::factory()->create();

    $today = now($user->timezone)->toDateString();
    statsSession($user, $mantraA, $today, 108);
    statsSession($user, $mantraB, $today, 54);

    $all = props($this->actingAs($user)->get('/stats?range=week'));
    $onlyA = props($this->actingAs($user)->get("/stats?range=week&mantra={$mantraA->id}"));

    expect($all['totals']['recitations'])->toBe(162)
        ->and($onlyA['totals']['recitations'])->toBe(108)
        ->and($onlyA['streak']['current'])->toBe(1);
});

test('el desglose por mantra viene ordenado descendente', function () {
    $user = User::factory()->create();
    $mantraA = Mantra::factory()->create(['name' => 'Menor']);
    $mantraB = Mantra::factory()->create(['name' => 'Mayor']);

    $today = now($user->timezone)->toDateString();
    statsSession($user, $mantraA, $today, 54);
    statsSession($user, $mantraB, $today, 216);

    $data = props($this->actingAs($user)->get('/stats'));

    expect($data['byMantra'][0]['name'])->toBe('Mayor')
        ->and($data['byMantra'][0]['recitations'])->toBe(216)
        ->and($data['byMantra'][1]['recitations'])->toBe(54);
});

test('un rango inválido cae a week sin romper', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/stats?range=decade')->assertOk();
});

test('el dashboard de otro usuario no filtra datos ajenos', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mantra = Mantra::factory()->create();

    statsSession($other, $mantra, now($other->timezone)->toDateString(), 500);

    $data = props($this->actingAs($user)->get('/stats'));

    expect($data['totals']['recitations'])->toBe(0)
        ->and($data['byMantra'])->toBeEmpty();
});
