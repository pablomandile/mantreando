<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import BellIcon from '@/components/icons/BellIcon.vue';
import EndlessKnotIcon from '@/components/icons/EndlessKnotIcon.vue';
import VajraIcon from '@/components/icons/VajraIcon.vue';
import { hapticTick } from '@/lib/mala/haptics';
import {
    applyMove,
    BEADS_PER_ROW,
    digitsOf,
    ROW_VALUES,
} from '@/lib/retreat/abacus';
import type { RowIndex } from '@/lib/retreat/abacus';

/**
 * El contador del retiro, tal cual el de madera: tres líneas de diez cuentas
 * que se corren de izquierda a derecha.
 *
 * No reusa MalaBead: esa cuenta está posicionada en absoluto dentro de la
 * columna vertical virtualizada del mala, atada a --pitch y al pool de nodos
 * de useMala. Acá son 30 nodos fijos en horizontal. Lo que sí se comparte es
 * la receta visual de la esfera (.bead-sphere, en app.css).
 */
const props = defineProps<{
    count: number;
    /** Sin etapa en curso no hay nada que contar. */
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:count': [value: number] }>();

const rows: RowIndex[] = [0, 1, 2];
const slots = Array.from({ length: BEADS_PER_ROW }, (_, index) => index);

// Cuántas cuentas están corridas en cada línea. El acarreo no se calcula: es
// una consecuencia de que las líneas sean las cifras del conteo.
const moved = computed(() => digitsOf(props.count));

const rowLabels: Record<RowIndex, string> = {
    0: 'Unidades',
    1: 'Decenas',
    2: 'Centenas',
};

// Un símbolo al final de cada línea, de abajo hacia arriba: vajra, campana y
// nudo eterno. Las filas se pintan en el orden del array `rows` (0,1,2) sin
// invertir, así que la última en el DOM —centenas— es la de más abajo.
const rowIcons: Record<RowIndex, Component> = {
    2: VajraIcon,
    1: BellIcon,
    0: EndlessKnotIcon,
};

/** Una cuenta está del lado derecho si entra en las últimas `moved`. */
function isMoved(row: RowIndex, slot: number): boolean {
    return slot >= BEADS_PER_ROW - moved.value[row];
}

// ── Gesto ───────────────────────────────────────────────────────────────────
// Alcanza con empezar el movimiento: pasado el umbral se confirma la cuenta y
// la transición de CSS completa la corrida sola. Una cuenta por gesto: para
// la siguiente hay que levantar el dedo y volver a empujar.
const DRAG_THRESHOLD = 10;

const dragging = ref<RowIndex | null>(null);
let startX = 0;
let fired = false;

function onPointerDown(event: PointerEvent, row: RowIndex): void {
    if (props.disabled) {
        return;
    }

    dragging.value = row;
    startX = event.clientX;
    fired = false;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
    const row = dragging.value;

    if (row === null || fired) {
        return;
    }

    const dx = event.clientX - startX;

    if (Math.abs(dx) < DRAG_THRESHOLD) {
        return;
    }

    fired = true;
    commit(row, dx > 0 ? 1 : -1);
}

function onPointerUp(event: PointerEvent): void {
    const target = event.currentTarget as HTMLElement;

    if (target.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    dragging.value = null;
}

function commit(row: RowIndex, direction: 1 | -1): void {
    const next = applyMove(props.count, row, direction);

    if (next === props.count) {
        return;
    }

    hapticTick();
    emit('update:count', next);
}

/** Teclado: sin el gesto, el contador sería inoperable con teclas. */
function onKeydown(event: KeyboardEvent, row: RowIndex): void {
    if (props.disabled) {
        return;
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        commit(row, 1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        commit(row, -1);
    }
}
</script>

<template>
    <div class="abacus" :aria-disabled="disabled || undefined">
        <div v-for="row in rows" :key="row" class="abacus-row-track">
            <div
                class="abacus-row"
                :class="{ 'abacus-row--dragging': dragging === row }"
                :data-disabled="disabled || undefined"
                tabindex="0"
                role="button"
                :aria-label="
                    t(':row: :moved de 10. Cada cuenta suma :value.', {
                        row: t(rowLabels[row]),
                        moved: String(moved[row]),
                        value: ROW_VALUES[row].toLocaleString('es'),
                    })
                "
                @pointerdown="onPointerDown($event, row)"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @keydown="onKeydown($event, row)"
            >
                <span class="abacus-cord" aria-hidden="true" />
                <span
                    v-for="slot in slots"
                    :key="slot"
                    class="abacus-bead"
                    :style="{ '--slot': isMoved(row, slot) ? slot + 1 : slot }"
                    aria-hidden="true"
                >
                    <span class="bead-sphere" />
                </span>
            </div>
            <component :is="rowIcons[row]" class="abacus-row-icon" />
        </div>
    </div>
</template>

<style scoped>
.abacus {
    /* Once posiciones: diez cuentas más el hueco que deja la que se corrió. */
    --slots: 11;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    /* Del ancho depende el tamaño de las cuentas: pasado esto quedan enormes
       y el gesto se vuelve incómodo. */
    max-width: 26rem;
    margin-inline: auto;
    padding: 0.75rem 0.5rem;
    border-radius: 0.75rem;
    border: 1px solid var(--border);
    background-color: color-mix(
        in oklab,
        var(--mantra-color, var(--muted)) 8%,
        transparent
    );
    touch-action: none;
    user-select: none;
}

/* La fila de cuentas y su símbolo, lado a lado: el símbolo no es decorado
   suelto, es lo que le deja aire a las cuentas hasta el borde derecho de la
   tarjeta en vez de que la última terminara pegada al marco. */
.abacus-row-track {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.abacus-row {
    position: relative;
    /* No 100%: ahora comparte la fila con el símbolo. flex-basis 0 más
       flex-grow reparte el ancho disponible y aspect-ratio saca la altura
       de lo que le toque, no del contenedor entero. */
    flex: 1 1 0%;
    min-width: 0;
    /* La altura sale del ancho: cada cuenta es un círculo de un slot, así que
       la fila mide once cuentas de ancho por una de alto. Nada de height:
       le ganaría al aspect-ratio y dejaría las cuentas en un punto. */
    aspect-ratio: var(--slots) / 1;
    cursor: grab;
    border-radius: 0.5rem;
    outline-offset: 2px;
}

.abacus-row-icon {
    flex: 0 0 auto;
    width: 1.5rem;
    height: 1.5rem;
    color: var(--mantra-color, var(--muted-foreground));
    opacity: 0.85;
}

.abacus-row:focus-visible {
    outline: 2px solid var(--ring);
}

.abacus-row--dragging {
    cursor: grabbing;
}

.abacus-row[data-disabled] {
    cursor: default;
    opacity: 0.4;
}

/* El cordón por el que corren las cuentas. */
.abacus-cord {
    position: absolute;
    top: 50%;
    left: 2%;
    right: 2%;
    height: 2px;
    transform: translateY(-50%);
    border-radius: 2px;
    background-color: color-mix(in oklab, var(--foreground) 45%, transparent);
}

.abacus-bead {
    position: absolute;
    top: 0;
    left: 0;
    width: calc(100% / var(--slots));
    aspect-ratio: 1;
    transform: translateX(calc(var(--slot) * 100%));
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
}

/* El aire entre cuentas va acá y no como padding de .abacus-bead: los
   porcentajes de padding se resuelven contra el ancho del BLOQUE CONTENEDOR
   (la fila entera), no contra la cuenta, y se comían el espacio. Estos sí
   miden contra la cuenta. Misma proporción que la hebra del mala (0.82). */
.abacus-bead > .bead-sphere {
    display: block;
    width: 82%;
    height: 82%;
    margin: 9%;
}

@media (prefers-reduced-motion: reduce) {
    .abacus-bead {
        transition: none;
    }
}
</style>
