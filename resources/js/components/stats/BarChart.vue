<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Barras de una sola serie (magnitud → un solo tono, validado en ambos
 * modos). SVG a mano: marcas finas con tope redondeado 4px ancladas a la
 * baseline, 2px de aire entre barras, grilla recesiva, tooltip por barra
 * y label directo solo en el pico. Colores por CSS vars (.viz-root).
 */

interface Bucket {
    key: string;
    value: number;
}

const props = defineProps<{
    data: Bucket[];
    granularity: 'day' | 'month';
}>();

const W = 600;
const H = 190;
const PAD_TOP = 22;
const PAD_BOTTOM = 24;
const PAD_LEFT = 8;
const PAD_RIGHT = 8;

const plotH = H - PAD_TOP - PAD_BOTTOM;
const plotW = W - PAD_LEFT - PAD_RIGHT;

const maxValue = computed(() => Math.max(1, ...props.data.map((d) => d.value)));

/** Techo "lindo" para la escala y las gridlines. */
const niceMax = computed(() => {
    const raw = maxValue.value;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const candidates = [1, 2, 2.5, 5, 10].map((m) => m * magnitude);

    return candidates.find((c) => c >= raw) ?? raw;
});

const peakIndex = computed(() => {
    const values = props.data.map((d) => d.value);
    const max = Math.max(...values);

    return max > 0 ? values.indexOf(max) : -1;
});

const slot = computed(() => plotW / Math.max(1, props.data.length));
const barWidth = computed(() => Math.max(3, Math.min(28, slot.value - 2))); // 2px de aire

function x(i: number): number {
    return PAD_LEFT + i * slot.value + (slot.value - barWidth.value) / 2;
}

function barHeight(value: number): number {
    return (value / niceMax.value) * plotH;
}

/** Barra con tope redondeado (4px) anclada a la baseline. */
function barPath(i: number, value: number): string {
    const bx = x(i);
    const bw = barWidth.value;
    const bh = barHeight(value);
    const r = Math.min(4, bw / 2, bh);
    const top = PAD_TOP + plotH - bh;
    const bottom = PAD_TOP + plotH;

    return [
        `M ${bx} ${bottom}`,
        `V ${top + r}`,
        `Q ${bx} ${top} ${bx + r} ${top}`,
        `H ${bx + bw - r}`,
        `Q ${bx + bw} ${top} ${bx + bw} ${top + r}`,
        `V ${bottom}`,
        'Z',
    ].join(' ');
}

const gridValues = computed(() => [niceMax.value / 2, niceMax.value]);

function gridY(value: number): number {
    return PAD_TOP + plotH - (value / niceMax.value) * plotH;
}

// ── Labels del eje X ────────────────────────────────────────────────────────

const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const WEEKDAYS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

function shortLabel(bucket: Bucket): string {
    if (props.granularity === 'month') {
        return MONTHS[Number(bucket.key.slice(5, 7)) - 1] ?? bucket.key;
    }

    // 'YYYY-MM-DD' → día del mes; en rango semana, día de la semana
    const day = Number(bucket.key.slice(8, 10));

    if (props.data.length <= 7) {
        const date = new Date(`${bucket.key}T12:00:00`);

        return WEEKDAYS[date.getDay()];
    }

    return String(day);
}

/** En rangos densos (30 días) mostrar 1 de cada 5 labels. */
function showLabel(i: number): boolean {
    if (props.data.length <= 12) {
        return true;
    }

    return i % 5 === 0 || i === props.data.length - 1;
}

function fullLabel(bucket: Bucket): string {
    if (props.granularity === 'month') {
        return `${MONTHS[Number(bucket.key.slice(5, 7)) - 1]} ${bucket.key.slice(0, 4)}`;
    }

    const date = new Date(`${bucket.key}T12:00:00`);

    return date.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
}

// ── Tooltip ─────────────────────────────────────────────────────────────────

const hovered = ref<number | null>(null);

const tooltipStyle = computed(() => {
    if (hovered.value === null) {
        return {};
    }

    const centerPct = ((x(hovered.value) + barWidth.value / 2) / W) * 100;

    return {
        left: `${Math.min(86, Math.max(14, centerPct))}%`,
    };
});
</script>

<template>
    <div class="viz-root relative">
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            class="w-full"
            role="img"
            aria-label="Recitaciones por período"
        >
            <!-- grilla recesiva -->
            <g>
                <line
                    v-for="value in gridValues"
                    :key="value"
                    :x1="PAD_LEFT"
                    :x2="W - PAD_RIGHT"
                    :y1="gridY(value)"
                    :y2="gridY(value)"
                    class="viz-grid"
                />
                <text
                    v-for="value in gridValues"
                    :key="`t${value}`"
                    :x="W - PAD_RIGHT"
                    :y="gridY(value) - 4"
                    text-anchor="end"
                    class="viz-axis-label"
                >
                    {{ value.toLocaleString('es') }}
                </text>
            </g>

            <!-- baseline -->
            <line
                :x1="PAD_LEFT"
                :x2="W - PAD_RIGHT"
                :y1="PAD_TOP + plotH"
                :y2="PAD_TOP + plotH"
                class="viz-baseline"
            />

            <!-- barras -->
            <g>
                <template v-for="(bucket, i) in data" :key="bucket.key">
                    <path
                        v-if="bucket.value > 0"
                        :d="barPath(i, bucket.value)"
                        class="viz-bar"
                        :class="{ 'viz-bar--dim': hovered !== null && hovered !== i }"
                    />
                </template>
            </g>

            <!-- label directo SOLO en el pico -->
            <text
                v-if="peakIndex >= 0 && hovered === null"
                :x="x(peakIndex) + barWidth / 2"
                :y="PAD_TOP + plotH - barHeight(data[peakIndex].value) - 6"
                text-anchor="middle"
                class="viz-peak-label"
            >
                {{ data[peakIndex].value.toLocaleString('es') }}
            </text>

            <!-- labels del eje X -->
            <g>
                <template v-for="(bucket, i) in data" :key="`x${bucket.key}`">
                    <text
                        v-if="showLabel(i)"
                        :x="x(i) + barWidth / 2"
                        :y="H - 8"
                        text-anchor="middle"
                        class="viz-axis-label"
                    >
                        {{ shortLabel(bucket) }}
                    </text>
                </template>
            </g>

            <!-- hit targets (más anchos que la marca) -->
            <g>
                <rect
                    v-for="(bucket, i) in data"
                    :key="`h${bucket.key}`"
                    :x="PAD_LEFT + i * slot"
                    :y="PAD_TOP"
                    :width="slot"
                    :height="plotH"
                    fill="transparent"
                    @mouseenter="hovered = i"
                    @mouseleave="hovered = null"
                >
                    <title>{{ fullLabel(bucket) }}: {{ bucket.value.toLocaleString('es') }}</title>
                </rect>
            </g>
        </svg>

        <!-- tooltip HTML -->
        <div
            v-if="hovered !== null"
            class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-md border bg-background px-2.5 py-1.5 text-xs shadow-sm"
            :style="tooltipStyle"
        >
            <span class="text-muted-foreground">{{ fullLabel(data[hovered]) }}</span>
            <span class="ml-2 font-medium tabular-nums">
                {{ data[hovered].value.toLocaleString('es') }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.viz-root {
    /* Tono único validado (slot naranja de la paleta de referencia) */
    --viz-bar: #eb6834;
    --viz-grid: #e1e0d9;
    --viz-baseline: #c3c2b7;
    --viz-muted: #898781;
}

:global(.dark) .viz-root {
    --viz-bar: #d95926;
    --viz-grid: #2c2c2a;
    --viz-baseline: #383835;
}

.viz-bar {
    fill: var(--viz-bar);
    transition: opacity 120ms ease-out;
}

.viz-bar--dim {
    opacity: 0.45;
}

.viz-grid {
    stroke: var(--viz-grid);
    stroke-width: 1;
}

.viz-baseline {
    stroke: var(--viz-baseline);
    stroke-width: 1;
}

.viz-axis-label {
    fill: var(--viz-muted);
    font-size: 10px;
}

.viz-peak-label {
    fill: currentColor;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}
</style>
