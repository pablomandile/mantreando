<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { ref } from 'vue';

interface Recitation {
    id: number;
    title: string;
    text: string;
}

defineProps<{
    recitations: Recitation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Otras recitaciones', href: '/recitations' }],
    },
});

// Varias abiertas a la vez: en una sesión se encadenan (los cuatro
// inconmensurables, después la promesa, después los votos).
const open = ref<Set<number>>(new Set());

function toggle(id: number): void {
    const next = new Set(open.value);

    if (!next.delete(id)) {
        next.add(id);
    }

    open.value = next;
}
</script>

<template>
    <Head :title="t('Otras recitaciones')" />

    <div class="space-y-6 px-4 py-6">
        <header>
            <h1 class="text-2xl font-semibold">
                {{ t('Otras recitaciones') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Oraciones y yogas para leer, sin contar en el mala.') }}
            </p>
        </header>

        <div class="flex flex-col gap-3">
            <div
                v-for="recitation in recitations"
                :key="recitation.id"
                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-xl p-4 text-left transition-colors hover:bg-accent/50"
                    :aria-expanded="open.has(recitation.id)"
                    :aria-controls="`recitation-${recitation.id}`"
                    @click="toggle(recitation.id)"
                >
                    <span class="font-medium">{{ recitation.title }}</span>
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': open.has(recitation.id) }"
                    />
                </button>

                <!-- whitespace-pre-line: el texto trae su propia estructura
                     de versos y párrafos desde el seeder. -->
                <p
                    v-show="open.has(recitation.id)"
                    :id="`recitation-${recitation.id}`"
                    class="px-4 pb-4 leading-relaxed font-light whitespace-pre-line"
                >
                    {{ recitation.text }}
                </p>
            </div>
        </div>
    </div>
</template>
