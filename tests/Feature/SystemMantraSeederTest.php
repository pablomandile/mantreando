<?php

use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\User;
use Database\Seeders\MantraCategorySeeder;
use Database\Seeders\SystemMantraSeeder;

test('el seed carga los 17 mantras del sistema con sus textos', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    expect(Mantra::whereNull('user_id')->count())->toBe(17);

    $byName = Mantra::whereNull('user_id')->get()->keyBy('name');

    expect($byName['Avalokiteshvara']->text)->toBe('OM MANI PEME HUM')
        ->and($byName['Shakyamuni']->text)->toBe('OM MUNI MUNI MAHA MUNIYE SOHA')
        ->and($byName['Tara Verde']->text)->toBe('OM TARE TUTTARE TURE SOHA')
        ->and($byName['Manjushri']->text)->toBe('OM AH RA PA TSA NA DHI')
        ->and($byName['Kandarohi']->text)->toBe('OM KHANDAROHI HUM HUM PHET')
        ->and($byName['Vajrasatva largo']->text)->toContain('MAHA SAMAYA SATTO AH HUM')
        ->and($byName['Dorje Shugden corto']->text)->toBe('OM VAJRA VIKI VITRANA SOHA')
        ->and($byName['Gueshe Kelsang Gyatso']->category->slug)->toBe('guru-yoga')
        ->and($byName['Vajrayoguini']->category->slug)->toBe('tantra')
        ->and($byName['Heruka']->category->slug)->toBe('tantra')
        ->and($byName['Buda de la medicina']->category->slug)->toBe('healing');
});

test('el seed es idempotente: correrlo dos veces no duplica', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);
    $this->seed(SystemMantraSeeder::class);

    expect(Mantra::whereNull('user_id')->count())->toBe(17);
});

test('los nombres legacy se renombran preservando el historial de práctica', function () {
    $this->seed(MantraCategorySeeder::class);

    // Base "vieja": un mantra del seed anterior con sesiones registradas
    $legacy = Mantra::factory()->create([
        'name' => 'Om Mani Padme Hum',
        'user_id' => null,
    ]);
    $user = User::factory()->create();
    $session = PracticeSession::factory()->create([
        'user_id' => $user->id,
        'mantra_id' => $legacy->id,
    ]);

    $this->seed(SystemMantraSeeder::class);

    // Mismo id, nombre nuevo, texto nuevo, sesión intacta
    $renamed = Mantra::find($legacy->id);
    expect($renamed->name)->toBe('Avalokiteshvara')
        ->and($renamed->text)->toBe('OM MANI PEME HUM')
        ->and($session->refresh()->mantra_id)->toBe($legacy->id)
        ->and(Mantra::whereNull('user_id')->where('name', 'like', '%Om Mani%')->count())->toBe(0)
        ->and(Mantra::whereNull('user_id')->count())->toBe(17);
});

test('los mantras del sistema traducen el nombre al inglés', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    app()->setLocale('en');

    $tara = Mantra::whereNull('user_id')->where('name', 'Tara Verde')->first();
    expect($tara->localized('name'))->toBe('Green Tara');

    app()->setLocale('es');
    expect($tara->localized('name'))->toBe('Tara Verde');
});
