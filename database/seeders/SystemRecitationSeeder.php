<?php

namespace Database\Seeders;

use App\Models\Recitation;
use Illuminate\Database\Seeder;

class SystemRecitationSeeder extends Seeder
{
    /**
     * Otras recitaciones que trae la app. El upsert va por slug, así que
     * corregir un título o un texto acá actualiza la fila en vez de duplicarla.
     *
     * Los textos respetan la redacción tal como los pasó el usuario. Solo se
     * corrigieron erratas evidentes de digitalización (¡Oh en vez de jOh,
     * "la"/"los" donde decía Ia/tos/bos, "luz clara" en vez de "ciara", un
     * "que" repetido) y se rearmaron los cortes de línea que venían del ancho
     * de columna del librito, no de la estructura del verso.
     */
    public function run(): void
    {
        $recitations = [
            [
                'slug' => 'yoga-conciso-seis-sesiones',
                'title' => 'Yoga conciso de las seis sesiones',
                'text' => 'En el Guru y en las Tres Joyas me refugio. Con un vajra y una campana me genero como la Deidad y hago ofrendas. Confío en los Dharmas del sutra y del tantra y me abstengo de cometer acciones perjudiciales. Practico todos los Dharmas virtuosos y ayudo a los seres migratorios con las cuatro prácticas del dar.',
            ],
            [
                'slug' => 'cuatro-inconmensurables',
                'title' => 'Los cuatro inconmensurables',
                'text' => 'Que todos los seres sean felices, que todos los seres se liberen del sufrimiento, que nadie sea desposeído de su felicidad, que todos los seres logren ecuanimidad, libres de odio y de apego.',
            ],
            [
                'slug' => 'promesa',
                'title' => 'Promesa',
                'text' => 'Desde este momento hasta que alcance el estado de un Buda voy a guardar, aunque me cueste la vida, la mente que desea alcanzar la iluminación completa para liberar a todos los seres de los miedos del samsara y de la paz solitaria.',
            ],
            [
                'slug' => 'votos-del-bodhisatva',
                'title' => 'Toma de los votos del Bodhisatva',
                'text' => "¡Oh, Guru Buda Shakyamuni!,\npor favor, escucha lo que te voy a decir:\nDesde este momento hasta que alcance la iluminación\nen las Tres Joyas —Buda, Dharma y Sangha— me refugio\ny confieso todas y cada una de mis malas acciones.\nMe regocijo de las virtudes de todos los seres\ny me comprometo a realizar la iluminación de un Buda.",
            ],
            [
                'slug' => 'votos-tantricos',
                'title' => 'Toma de los votos tántricos',
                'text' => "¡Oh, Guru Buda Shakyamuni!,\npor favor, escucha lo que te voy a decir:\nDesde este momento hasta que alcance la iluminación,\npor el beneficio de todos los seres sintientes\nvoy a mantener los votos y compromisos generales y específicos\nde las cinco familias de Budas.\nVoy a salvar de los renacimientos inferiores a los que no han sido salvados,\nliberar de los renacimientos del samsara a los que no han sido liberados,\ndar aliento —la vida espiritual del vajrayana— a los que no pueden practicar el camino Vajrayana\ny guiar a todos los seres al estado más allá del dolor, el estado de la iluminación.",
            ],
            [
                'slug' => 'yoga-experimentar-nectar',
                'title' => 'Yoga de experimentar néctar',
                'text' => "Transformación\nOM AH HUM (x3)\n\nDedicación\nDedico esto a la joya de Buda\n\nReconocimiento\nNéctar que cura las enfermedades\nNéctar que cura la muerte\nNéctar que cura la ignorancia",
            ],
            [
                'slug' => 'yoga-del-dormir',
                'title' => 'Yoga del dormir',
                'text' => "«Para beneficiar a todos los seres sintientes\nvoy a convertirme en el Buda Vajrayoguini.\nPara ello voy a alcanzar la realización de la luz clara del gozo.»\n\nNuestro cuerpo, nuestro yo y todos los demás fenómenos que normalmente percibimos no existen. Intentamos percibir la mera ausencia de todos los fenómenos que normalmente percibimos, la vacuidad de todos los fenómenos.\n\nEn el vasto espacio de la vacuidad de todos los fenómenos —la tierra pura de Keajra—, aparezco como Vajrayoguini rodeada por los Héroes y Heroínas iluminados. Aunque muestro esta apariencia, no es otra que la vacuidad de todos los fenómenos.",
            ],
            [
                'slug' => 'ocho-versos-madre-sanscrito',
                'title' => 'Los ocho versos de alabanza a la Madre (sánscrito)',
                'text' => "OM NAMO BHAGAVATI VAJRA VARAHI\nBAM HUM HUM PHET\n\nOM NAMO ARYA APARADZSITE TRE LOKIA\nMATI BIYE SHORI HUM HUM PHET\n\nOM NAMA SARVA BUTA BHAYA VAHI\nMAHA VAJRE HUM HUM PHET\n\nOM NAMO VAJRA SANI ADZSITE\nAPARADZSITE VASHAM KARANITRA\nHUM HUM PHET\n\nOM NAMO BHRAMANI SHOKANI ROKANI\nKROTE KARALINI HUM HUM PHET\n\nOM NAMA DRASANI MARANI PRABHE\nDANI PARADZSAYE HUM HUM PHET\n\nOM NAMO BIDZSAYE DZSAMBHANI\nTAMBHANI MOHANI HUM HUM PHET\n\nOM NAMO VAJRA VARAHI MAHA YOGUINI\nKAME SHORI KHAGE HUM HUM PHET",
            ],
            [
                'slug' => 'ocho-versos-madre',
                'title' => 'Los ocho versos de alabanza a la Madre',
                'text' => "OM me postro ante Vajravarahi, la Madre Bienaventurada HUM HUM PHET\n\nOM ante la poderosa y Noble Dama de Conocimiento, invencible en los tres reinos HUM HUM PHET\n\nOM ante ti que destruyes todo temor a los espíritus malignos con tu gran vajra HUM HUM PHET\n\nOM ante ti, de ojos subyugantes, que permaneces invicta como el asiento vajra HUM HUM PHET\n\nOM ante ti que desecas a Brahma con tu forma furiosa y colérica HUM HUM PHET\n\nOM ante ti que aterrorizas y acallas a los demonios, y conquistas a los que moran en otras latitudes HUM HUM PHET\n\nOM ante ti que conquistas a los que causan ofuscación, rigidez y confusión HUM HUM PHET\n\nOM me postro ante ti, Vajravarahi, la Gran Madre, la consorte Dakini que satisface todos los deseos HUM HUM PHET",
            ],
        ];

        foreach ($recitations as $position => $data) {
            Recitation::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'text' => $data['text'],
                    'position' => $position + 1,
                ],
            );
        }
    }
}
