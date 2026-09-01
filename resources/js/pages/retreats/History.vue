<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, ChevronDown, Trash2 } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { reactive, ref } from 'vue';
import ImageLightbox from '@/components/ImageLightbox.vue';

interface Stage {
    id: number;
    name: string;
    goal: number;
    count: number;
    completed_on: string | null;
}

interface HistoryEntry {
    id: number;
    deity: {
        id: number;
        name: string;
        image_url: string | null;
        syllable_image_url: string | null;
        color: string | null;
    };
    started_on: string;
    completed_on: string | null;
    first_counted_on: string | null;
    last_counted_on: string | null;
    archived_on: string | null;
    notes: string | null;
    dedications: string | null;
    stages: Stage[];
}

defineProps<{
    retreats: HistoryEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Retiro de aproximación', href: '/retreats' },
            { title: 'Historial', href: '/retreats/history' },
        ],
    },
});

function formatNumber(value: number): string {
    return value.toLocaleString('es');
}

/** Mediodía, no medianoche: evita que la fecha se corra de día por la zona. */
function formatDate(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString('es', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

const PREVIEW_CHARS = 220;

function isLong(text: string): boolean {
    return text.trim().length > PREVIEW_CHARS;
}

// Un set por campo (notas/dedicaciones) y por retiro: cada tarjeta despliega
// su propio texto sin afectar a las demás.
const expandedNotes = reactive(new Set<number>());
const expandedDedications = reactive(new Set<number>());

function toggle(set: Set<number>, id: number): void {
    if (set.has(id)) {
        set.delete(id);
    } else {
        set.add(id);
    }
}

const deleting = ref<number | null>(null);

function destroy(entry: HistoryEntry): void {
    if (
        !confirm(
            t(
                '¿Eliminar este retiro del historial? Se pierde todo lo guardado: notas, dedicaciones y el conteo de cada etapa.',
            ),
        )
    ) {
        return;
    }

    deleting.value = entry.id;
    router.delete(`/retreats/${entry.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('Historial de retiros')" />

    <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-6">
        <header>
            <Link
                href="/retreats"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ t('Retiro de aproximación') }}
            </Link>
            <h1 class="text-2xl font-semibold">
                {{ t('Historial de retiros') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Los retiros que ya guardaste, con todos sus datos.') }}
            </p>
        </header>

        <p
            v-if="retreats.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('Todavía no guardaste ningún retiro.') }}
        </p>

        <div v-else class="flex flex-col gap-4">
            <article
                v-for="entry in retreats"
                :key="entry.id"
                class="mantra-card space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                :data-color="entry.deity.color ?? undefined"
            >
                <div class="flex items-start gap-3">
                    <ImageLightbox
                        v-if="entry.deity.image_url"
                        :src="entry.deity.image_url"
                        :alt="entry.deity.name"
                        class="size-14 shrink-0 rounded-lg object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <h2 class="font-medium">{{ entry.deity.name }}</h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t('Del :start al :end', {
                                    start: formatDate(
                                        entry.first_counted_on ??
                                            entry.started_on,
                                    ),
                                    end: formatDate(
                                        entry.completed_on ??
                                            entry.archived_on ??
                                            entry.started_on,
                                    ),
                                })
                            }}
                        </p>
                        <p
                            v-if="entry.archived_on"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                t('Guardado el :date', {
                                    date: formatDate(entry.archived_on),
                                })
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="deleting === entry.id"
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-destructive disabled:opacity-50"
                        :aria-label="t('Eliminar del historial')"
                        :title="t('Eliminar del historial')"
                        @click="destroy(entry)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <ol class="space-y-1">
                    <li
                        v-for="(item, index) in entry.stages"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                    >
                        <span class="min-w-0 truncate">
                            {{ index + 1 }}. {{ item.name }}
                        </span>
                        <span class="shrink-0 tabular-nums">
                            <Check
                                v-if="item.completed_on"
                                class="inline size-4"
                            />
                            {{ formatNumber(item.count) }}
                            /
                            {{ formatNumber(item.goal) }}
                        </span>
                    </li>
                </ol>

                <div v-if="entry.notes" class="space-y-1">
                    <h3 class="text-xs font-medium text-muted-foreground">
                        {{ t('Notas') }}
                    </h3>
                    <p
                        class="text-sm leading-relaxed whitespace-pre-line"
                        :class="{
                            'line-clamp-3': !expandedNotes.has(entry.id),
                        }"
                    >
                        {{ entry.notes }}
                    </p>
                    <button
                        v-if="isLong(entry.notes)"
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        @click="toggle(expandedNotes, entry.id)"
                    >
                        {{
                            expandedNotes.has(entry.id)
                                ? t('Mostrar menos')
                                : t('Mostrar más')
                        }}
                        <ChevronDown
                            class="size-3.5 transition-transform"
                            :class="{
                                'rotate-180': expandedNotes.has(entry.id),
                            }"
                        />
                    </button>
                </div>

                <div v-if="entry.dedications" class="space-y-1">
                    <h3 class="text-xs font-medium text-muted-foreground">
                        {{ t('Dedicaciones del retiro') }}
                    </h3>
                    <p
                        class="text-sm leading-relaxed whitespace-pre-line"
                        :class="{
                            'line-clamp-3': !expandedDedications.has(entry.id),
                        }"
                    >
                        {{ entry.dedications }}
                    </p>
                    <button
                        v-if="isLong(entry.dedications)"
                        type="button"
                        class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        @click="toggle(expandedDedications, entry.id)"
                    >
                        {{
                            expandedDedications.has(entry.id)
                                ? t('Mostrar menos')
                                : t('Mostrar más')
                        }}
                        <ChevronDown
                            class="size-3.5 transition-transform"
                            :class="{
                                'rotate-180': expandedDedications.has(entry.id),
                            }"
                        />
                    </button>
                </div>
            </article>
        </div>
    </div>
</template>
