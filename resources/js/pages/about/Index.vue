<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Globe, Mail } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';

/**
 * Quién hizo la app y cómo encontrarlo. Página propia del menú y no un ajuste:
 * acá no se configura nada. Sin props: la ruta es un Route::inertia.
 */

const AUTHOR = 'Pablo Mandile';
const EMAIL = 'pablo.mandile@gmail.com';
const WEBSITE = 'https://pablo.mandile.com.ar';
/** El mismo loto de las pantallas de felicitación. */
const LOTUS_SRC = '/img/decoratios/loto.webp';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Acerca de', href: '/about' }],
    },
});
</script>

<template>
    <Head :title="t('Acerca de')" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
        <header>
            <h1 class="text-2xl font-semibold sm:text-3xl">
                {{ t('Acerca de') }}
            </h1>
            <p class="text-base text-muted-foreground sm:text-lg">
                {{ t('Quién hizo esta app') }}
            </p>
        </header>

        <div class="space-y-5">
            <p class="text-xl font-medium sm:text-2xl">
                {{ t('Creado por :name', { name: AUTHOR }) }}
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <!-- El mail queda con el texto un paso más chico que el resto:
                     una dirección larga en text-lg se parte en dos líneas en
                     pantallas angostas. -->
                <a
                    :href="`mailto:${EMAIL}`"
                    class="inline-flex items-center gap-2.5 rounded-full border px-4 py-2 text-sm transition-colors hover:bg-accent sm:text-base"
                >
                    <Mail class="size-5 shrink-0" aria-hidden="true" />
                    {{ EMAIL }}
                </a>

                <!-- Solo el ícono: el sitio se reconoce por el globo y el
                     nombre del autor ya está arriba. El texto va en el
                     aria-label para que el lector de pantalla lo anuncie. -->
                <a
                    :href="WEBSITE"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex size-11 items-center justify-center rounded-full border transition-colors hover:bg-accent"
                    :aria-label="t('Mi sitio web')"
                    :title="t('Mi sitio web')"
                >
                    <Globe class="size-5" aria-hidden="true" />
                </a>
            </div>

            <p class="text-lg text-muted-foreground sm:text-xl">
                {{ t('Me gusta crear aplicaciones ;)') }}
            </p>
        </div>

        <!-- Decorativo: alt vacío para que el lector de pantalla lo saltee. -->
        <img
            :src="LOTUS_SRC"
            alt=""
            loading="lazy"
            class="mx-auto w-40 max-w-[45%] object-contain pt-4 opacity-80 sm:w-48"
        />
    </div>
</template>
