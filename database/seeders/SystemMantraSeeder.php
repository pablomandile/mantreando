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
            ],
            [
                'name' => 'Shakyamuni',
                'text' => 'OM MUNI MUNI MAHA MUNIYE SOHA',
                'category' => 'wisdom',
                'en' => 'Shakyamuni',
            ],
            [
                'name' => 'Avalokiteshvara',
                'text' => 'OM MANI PEME HUM',
                'category' => 'compassion',
                'en' => 'Avalokiteshvara',
            ],
            [
                'name' => 'Manjushri',
                'text' => 'OM AH RA PA TSA NA DHI',
                'category' => 'wisdom',
                'en' => 'Manjushri',
            ],
            [
                'name' => 'Vajrapani',
                'text' => 'OM AH VAJRAPANI HUM HUM PHET',
                'category' => 'protection',
                'en' => 'Vajrapani',
            ],
            [
                'name' => 'Tara Verde',
                'text' => 'OM TARE TUTTARE TURE SOHA',
                'category' => 'protection',
                'en' => 'Green Tara',
            ],
            [
                'name' => 'Tara Blanca',
                'text' => 'OM TARE TUTTARE TURE MAMA AYUR PUNIE GYANA PUTRIM KURU YE SOHA',
                'category' => 'healing',
                'en' => 'White Tara',
            ],
            [
                'name' => 'Prajnaparamita',
                'text' => 'TAYATHA OM GATE GATE PARAGATE PARASAMGATE BODHI SOHA',
                'category' => 'wisdom',
                'en' => 'Prajnaparamita',
            ],
            [
                'name' => 'Vajrayoguini',
                'text' => 'OM OM OM SARVA BUDDHA DAKINIYE VAJRA VARNANIYE VAJRA BEROTZSANIYE HUM HUM HUM PHET PHET PHET SOHA',
                'category' => 'tantra',
                'en' => 'Vajrayogini',
            ],
            [
                'name' => 'Heruka',
                'text' => 'OM SHRI VAJRA HE HE RU RU KAM HUM HUM PHET DAKINI DZSALA SHAMBARAM SOHA',
                'category' => 'tantra',
                'en' => 'Heruka',
            ],
            [
                'name' => 'Vajrasatva corto',
                'text' => 'OM VAJRA SATTO SARVA SIDDHI HUM',
                'category' => 'purification',
                'en' => 'Vajrasattva (short)',
            ],
            [
                'name' => 'Vajrasatva largo',
                'text' => "OM VAJRA SATTO SAMAYA, MANU PALAYA,\nVAJRA SATTO, TENO PATITA, DRIDHO ME\nBHAUA, SUTO KAYO MEBHAUA, SUPO KAYO\nME BHAUA, ANURAKTO ME BHAUA, SARVA\nSIDDHI ME PRAYATZSA, SARVA KARMA\nSUTZSA ME, TZSITAM SHRIYAM KURU HUM,\nHA HA HA HA HO, BHAGAVEN, SARVA\nTATHAGATA, VAJRA MA ME MUNTSA, VAJRA\nBHAUA, MAHA SAMAYA SATTO AH HUM\nPHET",
                'category' => 'purification',
                'en' => 'Vajrasattva (long)',
            ],
            [
                'name' => 'Buda de la medicina',
                'text' => 'TAYATHA OM BEKHANDZSE BEKHANDZSE MAHA BEKHANDZSE BEKHANDZSE RANDZSAYA SAMUGATE SOHA',
                'category' => 'healing',
                'en' => 'Medicine Buddha',
            ],
            [
                'name' => 'Amitayus',
                'text' => 'OM NAMO BHAGAVATE APARIMITA AYUR GIANA SUMBINI TSITA TEDZSO RANDZSAYA TATHAGATAYA, ARJATE, SAMIAK SAMBUDAYA, TAYATHA, OM PUNIE PUNIE MAHA PUNIE APARIMITA PUNIE APARIMITA PUNIE GIANA SAMBHA ROPATSITE, OM SARVA SAMKARA PARISHUDDHA DHARMATE GAGANA SAMUGATE, SOBHAUA BISHUDDHE MAHA NAYA PARIUARE SOHA',
                'category' => 'healing',
                'en' => 'Amitayus',
            ],
            [
                'name' => 'Dorje Shugden corto',
                'text' => 'OM VAJRA VIKI VITRANA SOHA',
                'category' => 'protection',
                'en' => 'Dorje Shugden (short)',
            ],
            [
                'name' => 'Dorje Shugden largo',
                'text' => "OM DHARMAPALA MAHA RADZSA\nVAJRA BEGAVEN RUDRA: PENTSA\nKULA SARVA SHA TRUM MARAYA\nHUM PHET",
                'category' => 'protection',
                'en' => 'Dorje Shugden (long)',
            ],
            [
                'name' => 'Gueshe Kelsang Gyatso',
                'text' => 'OM AH GURU VAJRADHARA KALPABHADRA SAMUDRA SHRIBHADRA SARVA SIDHI HUM HUM',
                'category' => 'guru-yoga',
                'en' => 'Geshe Kelsang Gyatso',
            ],
            [
                'name' => 'Maitreya',
                'text' => 'OM MOHI MOHI MAHA MOHI SOHA',
                'category' => 'compassion',
                'en' => 'Maitreya',
            ],
            [
                'name' => 'Kandarohi',
                'text' => 'OM KHANDAROHI HUM HUM PHET',
                'category' => 'tantra',
                'en' => 'Khandarohi',
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
                ],
            );
        }
    }
}
