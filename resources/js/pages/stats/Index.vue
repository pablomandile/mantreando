<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BarChart from '@/components/stats/BarChart.vue';

interface Bucket {
    key: string;
    value: number;
}

const props = defineProps<{
    filters: { range: 'week' | 'month' | 'year'; mantra: number | null };
    mantras: { id: number; name: string }[];
    series: Bucket[];
    granularity: 'day' | 'month';
    totals: {
        recitations: number;
        malas: number;
        duration_seconds: number;
        sessions: number;
    };
    allTimeRecitations: number;
    streak: { current: number; max: number };
    byMantra: { id: number; name: string; recitations: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Estadísticas', href: '/stats' }],
    },
});

const RANGES = computed(
    () =>
        [
            { key: 'week', label: t('7 días') },
            { key: 'month', label: t('30 días') },
            { key: 'year', label: t('12 meses') },
        ] as const,
);

function apply(range: string, mantra: number | null): void {
    router.get(
        '/stats',
        { range, mantra: mantra ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onMantraChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    apply(props.filters.range, value ? Number(value) : null);
}

const durationLabel = computed(() => {
    const total = props.totals.duration_seconds;
    const hours = Math.floor(total / 3600);
    const minutes = Math.round((total % 3600) / 60);

    return hours > 0 ? `${hours} h ${minutes} min` : `${minutes} min`;
});

const maxByMantra = computed(() =>
    Math.max(1, ...props.byMantra.map((m) => m.recitations)),
);
</script>

<template>
    <Head :title="t('Estadísticas')" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
        <header>
            <h1 class="text-xl font-semibold">{{ t('Estadísticas') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Tu progreso, sin ruido.') }}
            </p>
        </header>

        <!-- fila de filtros -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex rounded-md border p-0.5">
                <button
                    v-for="range in RANGES"
                    :key="range.key"
                    type="button"
                    class="rounded px-3 py-1.5 text-xs transition-colors"
                    :class="
                        filters.range === range.key
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="apply(range.key, filters.mantra)"
                >
                    {{ range.label }}
                </button>
            </div>

            <select
                class="h-8 rounded-md border border-input bg-transparent px-2 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="t('Filtrar por mantra')"
                @change="onMantraChange"
            >
                <option value="" :selected="filters.mantra === null">
                    {{ t('Todos los mantras') }}
                </option>
                <option
                    v-for="mantra in mantras"
                    :key="mantra.id"
                    :value="mantra.id"
                    :selected="filters.mantra === mantra.id"
                >
                    {{ mantra.name }}
                </option>
            </select>
        </div>

        <!-- KPI row -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-2xl font-semibold">
                    {{ totals.recitations.toLocaleString('es') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('recitaciones en el período') }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-2xl font-semibold">
                    {{ totals.malas.toLocaleString('es') }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('malas completos') }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-2xl font-semibold">{{ durationLabel }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('tiempo de práctica') }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-2xl font-semibold">
                    {{ streak.current }}
                    <span class="text-sm font-normal text-muted-foreground">
                        {{ streak.current === 1 ? t('día') : t('días') }}
                    </span>
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ t('racha actual · máx :max', { max: String(streak.max) }) }}
                </p>
            </div>
        </div>

        <!-- serie temporal -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-3 text-sm font-medium">{{ t('Recitaciones') }}</h2>
            <BarChart :data="series" :granularity="granularity" />

            <details class="mt-3">
                <summary
                    class="cursor-pointer text-xs text-muted-foreground select-none"
                >
                    {{ t('Ver como tabla') }}
                </summary>
                <table class="mt-2 w-full text-xs">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="py-1 font-normal">{{ t('Período') }}</th>
                            <th class="py-1 text-right font-normal">
                                {{ t('Recitaciones') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="bucket in series"
                            :key="bucket.key"
                            class="border-t"
                        >
                            <td class="py-1">{{ bucket.key }}</td>
                            <td class="py-1 text-right tabular-nums">
                                {{ bucket.value.toLocaleString('es') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>
        </section>

        <!-- desglose por mantra -->
        <section
            v-if="byMantra.length > 0 && filters.mantra === null"
            class="rounded-xl border p-4"
        >
            <h2 class="mb-3 text-sm font-medium">{{ t('Por mantra') }}</h2>
            <ul class="space-y-2.5">
                <li v-for="mantra in byMantra" :key="mantra.id">
                    <div class="mb-1 flex items-baseline justify-between gap-3">
                        <span class="truncate text-sm">{{ mantra.name }}</span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{ mantra.recitations.toLocaleString('es') }}
                        </span>
                    </div>
                    <div class="viz-track h-1.5 overflow-hidden rounded-full">
                        <div
                            class="viz-fill h-full rounded-full"
                            :style="{
                                width: `${(mantra.recitations / maxByMantra) * 100}%`,
                            }"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <p class="text-center text-xs text-muted-foreground">
            {{
                t(':total recitaciones desde el comienzo', {
                    total: allTimeRecitations.toLocaleString('es'),
                })
            }}
        </p>
    </div>
</template>

<style scoped>
.viz-track {
    background: color-mix(in srgb, #eb6834 12%, transparent);
}

.viz-fill {
    background: #eb6834;
}

:global(.dark) .viz-fill {
    background: #d95926;
}

:global(.dark) .viz-track {
    background: color-mix(in srgb, #d95926 15%, transparent);
}
</style>
