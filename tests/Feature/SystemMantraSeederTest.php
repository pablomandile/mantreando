<?php

use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\User;
use Database\Seeders\MantraCategorySeeder;
use Database\Seeders\SystemMantraSeeder;

test('el seed carga los 19 mantras del sistema con sus textos', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    expect(Mantra::whereNull('user_id')->count())->toBe(19);

    $byName = Mantra::whereNull('user_id')->get()->keyBy('name');

    expect($byName['Avalokiteshvara']->text)->toBe('OM MANI PEME HUM')
        ->and($byName['Shakyamuni']->text)->toBe('OM MUNI MUNI MAHA MUNIYE SOHA')
        ->and($byName['Tara Verde']->text)->toBe('OM TARE TUTTARE TURE SOHA')
        ->and($byName['Tara Blanca']->text)->toBe('OM TARE TUTTARE TURE MAMA AYUR PUNIE GYANA PUTRIM KURU YE SOHA')
        ->and($byName['Manjushri']->text)->toBe('OM AH RA PA TSA NA DHI')
        ->and($byName['Kandarohi']->text)->toBe('OM KHANDAROHI HUM HUM PHET')
        ->and($byName['Vajrasatva largo']->text)->toContain('MAHA SAMAYA SATTO AH HUM')
        ->and($byName['Amitayus']->text)->toStartWith('OM NAMO BHAGAVATE APARIMITA AYUR GIANA')
        ->and($byName['Amitayus']->text)->toEndWith('MAHA NAYA PARIUARE SOHA')
        ->and($byName['Dorje Shugden corto']->text)->toBe('OM VAJRA VIKI VITRANA SOHA')
        ->and($byName['Gueshe Kelsang Gyatso']->category->slug)->toBe('guru-yoga')
        ->and($byName['Vajrayoguini']->category->slug)->toBe('tantra')
        ->and($byName['Heruka']->category->slug)->toBe('tantra')
        ->and($byName['Buda de la medicina']->category->slug)->toBe('healing')
        ->and($byName['Tara Blanca']->category->slug)->toBe('healing')
        ->and($byName['Amitayus']->category->slug)->toBe('healing');
});

test('el seed es idempotente: correrlo dos veces no duplica', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);
    $this->seed(SystemMantraSeeder::class);

    expect(Mantra::whereNull('user_id')->count())->toBe(19);
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
        ->and(Mantra::whereNull('user_id')->count())->toBe(19);
});

test('cada mantra del sistema queda vinculado a una imagen que existe', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    $mantras = Mantra::whereNull('user_id')->get();

    expect($mantras->whereNull('image_path'))->toBeEmpty();

    foreach ($mantras as $mantra) {
        $full = public_path($mantra->image_path);
        $thumb = public_path(dirname($mantra->image_path).'/thumb/'.basename($mantra->image_path));

        expect(file_exists($full))->toBeTrue("Falta la imagen {$mantra->image_path} ({$mantra->name})")
            ->and(file_exists($thumb))->toBeTrue("Falta la miniatura de {$mantra->name}");
    }

    // Las variantes corta y larga comparten la imagen del mismo buda
    $byName = $mantras->keyBy('name');
    expect($byName['Vajrasatva corto']->image_path)->toBe($byName['Vajrasatva largo']->image_path)
        ->and($byName['Dorje Shugden corto']->image_path)->toBe($byName['Dorje Shugden largo']->image_path);
});

test('la url de una imagen de la app no apunta al disco de subidas', function () {
    $this->seed(MantraCategorySeeder::class);
    $this->seed(SystemMantraSeeder::class);

    $mantra = Mantra::whereNull('user_id')->where('name', 'Amitayus')->first();

    expect($mantra->hasAppImage())->toBeTrue()
        ->and($mantra->image_url)->toEndWith('/img/budas/amitayus.jpg')
        ->and($mantra->image_url)->not->toContain('/storage/')
        ->and($mantra->image_thumb_url)->toEndWith('/img/budas/thumb/amitayus.jpg');
});

test('una imagen subida por el usuario sigue sirviendose desde el disco public', function () {
    $mantra = Mantra::factory()->create(['image_path' => 'mantras/7/foto.jpg']);

    expect($mantra->hasAppImage())->toBeFalse()
        ->and($mantra->image_url)->toContain('/storage/mantras/7/foto.jpg')
        // Sin miniatura propia, la tarjeta reusa la original
        ->and($mantra->image_thumb_url)->toBe($mantra->image_url);
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
