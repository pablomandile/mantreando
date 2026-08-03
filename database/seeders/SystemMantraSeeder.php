<?php

namespace Database\Seeders;

use App\Models\Mantra;
use App\Models\MantraCategory;
use Illuminate\Database\Seeder;

class SystemMantraSeeder extends Seeder
{
    /**
     * Mantras del sistema (user_id = null): compartidos por todos los usuarios.
     * Editables/ampliables desde el seed; las preferencias personales
     * (favoritos, compromisos) viven en la pivot mantra_user.
     */
    public function run(): void
    {
        $categories = MantraCategory::pluck('id', 'slug');

        $mantras = [
            [
                'name' => 'Om Mani Padme Hum',
                'original_name' => 'ཨོཾ་མ་ཎི་པདྨེ་ཧཱུྃ',
                'transliteration' => 'oṃ maṇi padme hūṃ',
                'text' => 'Om Mani Padme Hum',
                'translation' => 'Om, la joya en el loto, Hum',
                'description' => 'El mantra de Avalokiteshvara (Chenrezig), el Buda de la Compasión. Es el mantra más recitado del budismo tibetano.',
                'benefits' => 'Cultiva la compasión hacia todos los seres. Se dice que purifica los seis reinos de la existencia, una sílaba por cada reino.',
                'category' => 'compassion',
            ],
            [
                'name' => 'Mantra de Tara Verde',
                'original_name' => 'ཨོཾ་ཏཱ་རེ་ཏུཏྟཱ་རེ་ཏུ་རེ་སྭཱ་ཧཱ',
                'transliteration' => 'oṃ tāre tuttāre ture svāhā',
                'text' => 'Om Tare Tuttare Ture Soha',
                'translation' => 'Om, Tara, la que libera, veloz y valiente, Soha',
                'description' => 'El mantra de Tara Verde, la madre de todos los budas, protectora que actúa con rapidez.',
                'benefits' => 'Protege de los miedos y obstáculos. Ayuda a superar dificultades y desarrolla la energía activa de la compasión.',
                'category' => 'protection',
            ],
            [
                'name' => 'Mantra de la Perfección de la Sabiduría',
                'original_name' => 'गते गते पारगते पारसंगते बोधि स्वाहा',
                'transliteration' => 'gate gate pāragate pārasaṃgate bodhi svāhā',
                'text' => 'Gate Gate Paragate Parasamgate Bodhi Soha',
                'translation' => 'Ido, ido, ido más allá, completamente ido más allá, despertar, Soha',
                'description' => 'El mantra del Sutra del Corazón, esencia de la Perfección de la Sabiduría (Prajñāpāramitā).',
                'benefits' => 'Desarrolla la sabiduría que comprende la vacuidad. Condensa el camino completo hacia la iluminación.',
                'category' => 'wisdom',
            ],
            [
                'name' => 'Mantra del Buda de la Medicina',
                'original_name' => 'ཏདྱ་ཐཱ། ཨོཾ་བྷཻ་ཥ་ཛྱེ་བྷཻ་ཥ་ཛྱེ་མ་ཧཱ་བྷཻ་ཥ་ཛྱེ་རཱ་ཛ་ས་མུདྒ་ཏེ་སྭཱ་ཧཱ',
                'transliteration' => 'tadyathā oṃ bhaiṣajye bhaiṣajye mahābhaiṣajye rāja-samudgate svāhā',
                'text' => 'Tayata Om Bekandze Bekandze Maha Bekandze Radza Samudgate Soha',
                'translation' => 'Así: Om, medicina, medicina, gran medicina, rey supremo, Soha',
                'description' => 'El mantra de Sangye Menla, el Buda de la Medicina, de color azul lapislázuli.',
                'benefits' => 'Favorece la sanación física y mental, propia y de otros. Pacifica la enfermedad y el sufrimiento.',
                'category' => 'healing',
            ],
            [
                'name' => 'Mantra de Vajrasattva (cien sílabas)',
                'original_name' => 'ཨོཾ་བཛྲ་སཏྭ་ས་མ་ཡ།',
                'transliteration' => 'oṃ vajrasattva samayam anupālaya...',
                'text' => 'Om Benza Sato Samaya Manupalaya Benza Sato Tenopa Tishta Dridho Me Bhava Sutokayo Me Bhava Supokayo Me Bhava Anurakto Me Bhava Sarva Siddhi Me Prayatsa Sarva Karma Sutsa Me Tsittam Shriyam Kuru Hum Ha Ha Ha Ha Ho Bhagavan Sarva Tathagata Benza Ma Me Muntsa Benzi Bhava Maha Samaya Sato Ah',
                'translation' => 'Om Vajrasattva, mantén tu compromiso, permanece firme en mí...',
                'description' => 'El mantra de las cien sílabas de Vajrasattva, práctica principal de purificación en el budismo tibetano.',
                'benefits' => 'Purifica el karma negativo, las transgresiones y los oscurecimientos mentales.',
                'category' => 'purification',
            ],
            [
                'name' => 'Om Ah Hum',
                'original_name' => 'ཨོཾ་ཨཱཿཧཱུྃ',
                'transliteration' => 'oṃ āḥ hūṃ',
                'text' => 'Om Ah Hum',
                'translation' => 'Cuerpo, palabra y mente iluminados',
                'description' => 'Las tres sílabas semilla que representan el cuerpo, la palabra y la mente de todos los budas.',
                'benefits' => 'Bendice y purifica cuerpo, palabra y mente. Mantra sencillo, ideal para comenzar la práctica.',
                'category' => 'purification',
            ],
        ];

        $english = [
            'Om Mani Padme Hum' => [
                'name' => 'Om Mani Padme Hum',
                'translation' => 'Om, the jewel in the lotus, Hum',
                'description' => 'The mantra of Avalokiteshvara (Chenrezig), the Buddha of Compassion. It is the most recited mantra in Tibetan Buddhism.',
                'benefits' => 'Cultivates compassion toward all beings. It is said to purify the six realms of existence, one syllable per realm.',
            ],
            'Mantra de Tara Verde' => [
                'name' => 'Green Tara Mantra',
                'translation' => 'Om, Tara, she who liberates, swift and brave, Soha',
                'description' => 'The mantra of Green Tara, mother of all buddhas, a protector who acts swiftly.',
                'benefits' => 'Protects from fears and obstacles. Helps overcome difficulties and develops the active energy of compassion.',
            ],
            'Mantra de la Perfección de la Sabiduría' => [
                'name' => 'Perfection of Wisdom Mantra',
                'translation' => 'Gone, gone, gone beyond, completely gone beyond, awakening, Soha',
                'description' => 'The mantra of the Heart Sutra, essence of the Perfection of Wisdom (Prajñāpāramitā).',
                'benefits' => 'Develops the wisdom that understands emptiness. It condenses the entire path to enlightenment.',
            ],
            'Mantra del Buda de la Medicina' => [
                'name' => 'Medicine Buddha Mantra',
                'translation' => 'Thus: Om, medicine, medicine, great medicine, supreme king, Soha',
                'description' => 'The mantra of Sangye Menla, the Medicine Buddha, lapis-lazuli blue in color.',
                'benefits' => 'Supports physical and mental healing, one\'s own and others\'. Pacifies illness and suffering.',
            ],
            'Mantra de Vajrasattva (cien sílabas)' => [
                'name' => 'Vajrasattva Mantra (hundred syllables)',
                'translation' => 'Om Vajrasattva, keep your commitment, remain firm in me...',
                'description' => 'The hundred-syllable mantra of Vajrasattva, the main purification practice in Tibetan Buddhism.',
                'benefits' => 'Purifies negative karma, transgressions and mental obscurations.',
            ],
            'Om Ah Hum' => [
                'name' => 'Om Ah Hum',
                'translation' => 'Enlightened body, speech and mind',
                'description' => 'The three seed syllables representing the body, speech and mind of all buddhas.',
                'benefits' => 'Blesses and purifies body, speech and mind. A simple mantra, ideal to start the practice.',
            ],
        ];

        foreach ($mantras as $data) {
            $category = $data['category'];
            unset($data['category']);

            Mantra::updateOrCreate(
                ['name' => $data['name'], 'user_id' => null],
                [
                    ...$data,
                    'user_id' => null,
                    'category_id' => $categories[$category],
                    'translations' => isset($english[$data['name']])
                        ? ['en' => $english[$data['name']]]
                        : null,
                ],
            );
        }
    }
}
