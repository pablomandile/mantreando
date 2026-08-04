<?php

namespace Database\Seeders;

use App\Models\Mantra;
use App\Models\MantraCategory;
use Illuminate\Database\Seeder;

class SystemMantraSeeder extends Seeder
{
    /**
     * Renombres de seeds anteriores → nombre actual: así una base ya poblada
     * conserva el historial de práctica (sesiones/agregados apuntan al mismo
     * mantra) en vez de duplicar filas.
     *
     * @var array<string, string>
     */
    private const LEGACY_RENAMES = [
        'Om Mani Padme Hum' => 'Avalokiteshvara',
        'Mantra de Tara Verde' => 'Tara Verde',
        'Mantra de la Perfección de la Sabiduría' => 'Prajnaparamita',
        'Mantra del Buda de la Medicina' => 'Buda de la medicina',
        'Mantra de Vajrasattva (cien sílabas)' => 'Vajrasatva largo',
        'Om Ah Hum' => 'Cuerpo, palabra y mente iluminadas',
    ];

    /**
     * Carpeta de las imágenes que viajan con la app (public/img/budas), con
     * una miniatura cuadrada en thumb/. Dos mantras pueden compartir imagen
     * (las variantes corta y larga de un mismo buda).
     */
    private const IMAGE_DIR = 'img/budas';

    /**
     * Mantras del sistema (user_id = null): compartidos por todos los
     * usuarios, editables/ampliables desde este seed. Las preferencias
     * personales (favoritos, compromisos) viven en la pivot mantra_user.
     */
    public function run(): void
    {
        $categories = MantraCategory::pluck('id', 'slug');

        // Migrar nombres de seeds anteriores antes del upsert por nombre.
        foreach (self::LEGACY_RENAMES as $old => $new) {
            Mantra::whereNull('user_id')->where('name', $old)->update(['name' => $new]);
        }

        $mantras = [
            [
                'name' => 'Cuerpo, palabra y mente iluminadas',
                'text' => 'OM AH HUM',
                'category' => 'purification',
                'en' => 'Enlightened body, speech and mind',
                'image' => 'om-ah-hum',
            ],
            [
                'name' => 'Shakyamuni',
                'text' => 'OM MUNI MUNI MAHA MUNIYE SOHA',
                'category' => 'wisdom',
                'en' => 'Shakyamuni',
                'image' => 'shakyamuni',
            ],
            [
                'name' => 'Avalokiteshvara',
                'text' => 'OM MANI PEME HUM',
                'category' => 'compassion',
                'en' => 'Avalokiteshvara',
                'image' => 'avalokiteshvara',
            ],
            [
                'name' => 'Manjushri',
                'text' => 'OM AH RA PA TSA NA DHI',
                'category' => 'wisdom',
                'en' => 'Manjushri',
                'image' => 'manjushri',
            ],
            [
                'name' => 'Vajrapani',
                'text' => 'OM AH VAJRAPANI HUM HUM PHET',
                'category' => 'protection',
                'en' => 'Vajrapani',
                'image' => 'vajrapani',
            ],
            [
                'name' => 'Tara Verde',
                'text' => 'OM TARE TUTTARE TURE SOHA',
                'category' => 'protection',
                'en' => 'Green Tara',
                'image' => 'tara-verde',
            ],
            [
                'name' => 'Tara Blanca',
                'text' => 'OM TARE TUTTARE TURE MAMA AYUR PUNIE GYANA PUTRIM KURU YE SOHA',
                'category' => 'healing',
                'en' => 'White Tara',
                'image' => 'tara-blanca',
            ],
            [
                'name' => 'Prajnaparamita',
                'text' => 'TAYATHA OM GATE GATE PARAGATE PARASAMGATE BODHI SOHA',
                'category' => 'wisdom',
                'en' => 'Prajnaparamita',
                'image' => 'prajnaparamita',
            ],
            [
                'name' => 'Vajrayoguini',
                'text' => 'OM OM OM SARVA BUDDHA DAKINIYE VAJRA VARNANIYE VAJRA BEROTZSANIYE HUM HUM HUM PHET PHET PHET SOHA',
                'category' => 'tantra',
                'en' => 'Vajrayogini',
                'image' => 'vajrayoguini',
            ],
            [
                'name' => 'Heruka',
                'text' => 'OM SHRI VAJRA HE HE RU RU KAM HUM HUM PHET DAKINI DZSALA SHAMBARAM SOHA',
                'category' => 'tantra',
                'en' => 'Heruka',
                'image' => 'heruka',
            ],
            [
                'name' => 'Vajrasatva corto',
                'text' => 'OM VAJRA SATTO SARVA SIDDHI HUM',
                'category' => 'purification',
                'en' => 'Vajrasattva (short)',
                'image' => 'vajrasatva',
            ],
            [
                'name' => 'Vajrasatva largo',
                'text' => "OM VAJRA SATTO SAMAYA, MANU PALAYA,\nVAJRA SATTO, TENO PATITA, DRIDHO ME\nBHAUA, SUTO KAYO MEBHAUA, SUPO KAYO\nME BHAUA, ANURAKTO ME BHAUA, SARVA\nSIDDHI ME PRAYATZSA, SARVA KARMA\nSUTZSA ME, TZSITAM SHRIYAM KURU HUM,\nHA HA HA HA HO, BHAGAVEN, SARVA\nTATHAGATA, VAJRA MA ME MUNTSA, VAJRA\nBHAUA, MAHA SAMAYA SATTO AH HUM\nPHET",
                'category' => 'purification',
                'en' => 'Vajrasattva (long)',
                'image' => 'vajrasatva',
            ],
            [
                'name' => 'Buda de la medicina',
                'text' => 'TAYATHA OM BEKHANDZSE BEKHANDZSE MAHA BEKHANDZSE BEKHANDZSE RANDZSAYA SAMUGATE SOHA',
                'category' => 'healing',
                'en' => 'Medicine Buddha',
                'image' => 'buda-de-la-medicina',
            ],
            [
                'name' => 'Amitayus',
                'text' => 'OM NAMO BHAGAVATE APARIMITA AYUR GIANA SUMBINI TSITA TEDZSO RANDZSAYA TATHAGATAYA, ARJATE, SAMIAK SAMBUDAYA, TAYATHA, OM PUNIE PUNIE MAHA PUNIE APARIMITA PUNIE APARIMITA PUNIE GIANA SAMBHA ROPATSITE, OM SARVA SAMKARA PARISHUDDHA DHARMATE GAGANA SAMUGATE, SOBHAUA BISHUDDHE MAHA NAYA PARIUARE SOHA',
                'category' => 'healing',
                'en' => 'Amitayus',
                'image' => 'amitayus',
            ],
            [
                'name' => 'Dorje Shugden corto',
                'text' => 'OM VAJRA VIKI VITRANA SOHA',
                'category' => 'protection',
                'en' => 'Dorje Shugden (short)',
                'image' => 'dorje-shugden',
            ],
            [
                'name' => 'Dorje Shugden largo',
                'text' => "OM DHARMAPALA MAHA RADZSA\nVAJRA BEGAVEN RUDRA: PENTSA\nKULA SARVA SHA TRUM MARAYA\nHUM PHET",
                'category' => 'protection',
                'en' => 'Dorje Shugden (long)',
                'image' => 'dorje-shugden',
            ],
            [
                'name' => 'Gueshe Kelsang Gyatso',
                'text' => 'OM AH GURU VAJRADHARA KALPABHADRA SAMUDRA SHRIBHADRA SARVA SIDHI HUM HUM',
                'category' => 'guru-yoga',
                'en' => 'Geshe Kelsang Gyatso',
                'image' => 'gueshe-kelsang-gyatso',
            ],
            [
                'name' => 'Maitreya',
                'text' => 'OM MOHI MOHI MAHA MOHI SOHA',
                'category' => 'compassion',
                'en' => 'Maitreya',
                'image' => 'maitreya',
            ],
            [
                'name' => 'Kandarohi',
                'text' => 'OM KHANDAROHI HUM HUM PHET',
                'category' => 'tantra',
                'en' => 'Khandarohi',
                'image' => 'kandarohi',
            ],
        ];

        foreach ($mantras as $data) {
            Mantra::updateOrCreate(
                ['name' => $data['name'], 'user_id' => null],
                [
                    'user_id' => null,
                    'category_id' => $categories[$data['category']],
                    'text' => $data['text'],
                    'translations' => ['en' => ['name' => $data['en']]],
                    'image_path' => isset($data['image'])
                        ? self::IMAGE_DIR.'/'.$data['image'].'.jpg'
                        : null,
                ],
            );
        }
    }
}
