<script setup lang="ts">
import MalaBead from '@/components/mala/MalaBead.vue';
import type { PoolBead } from '@/composables/useMala';
import type { BeadMaterial } from '@/lib/mala/types';

/**
 * Hebra vertical del mala, pegada al borde derecho de la pantalla.
 *
 * Virtualización: SOLO los POOL_SIZE nodos del pool existen en el DOM.
 * Cada nodo tiene su `top` estático (cambia solo al reciclarse); todo el
 * desplazamiento es UNA transform compositor-only en .mala-container que
 * escribe useMala en su loop de rAF — cero layout/paint durante el momentum.
 *
 * La superficie de gesto ocupa TODO el viewport (zona del pulgar libre);
 * el HUD del spike se superpone con z-index mayor.
 */
const props = defineProps<{
    pool: PoolBead[];
    material: BeadMaterial;
    textureUrl?: string | null;
    setContainer: (el: HTMLElement | null) => void;
    setColumn: (el: HTMLElement | null) => void;
    onPointerDown: (event: PointerEvent) => void;
    onPointerMove: (event: PointerEvent) => void;
    onPointerUp: (event: PointerEvent) => void;
}>();

const columnStyle = () =>
    props.textureUrl ? { '--bead-texture': `url("${props.textureUrl}")` } : {};
</script>

<template>
    <div
        class="mala-surface"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
    >
        <div
            :ref="(el) => setColumn(el as HTMLElement | null)"
            class="mala-column"
            :data-material="material"
            :style="columnStyle()"
        >
            <div class="mala-string" />
            <div
                :ref="(el) => setContainer(el as HTMLElement | null)"
                class="mala-container"
            >
                <div
                    v-for="bead in pool"
                    :key="bead.id"
                    class="mala-node"
                    :style="{ top: `${bead.top}px` }"
                >
                    <MalaBead :is-guru="bead.isGuru" :active="bead.active" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mala-surface {
    position: fixed;
    inset: 0;
    /* Sin esto el navegador se roba el gesto (scroll/pull-to-refresh). */
    touch-action: none;
    overscroll-behavior: none;
    user-select: none;
    -webkit-user-select: none;
    cursor: grab;
}

.mala-surface:active {
    cursor: grabbing;
}

.mala-column {
    --pitch: 66px;
    position: absolute;
    top: 0;
    bottom: 0;
    right: env(safe-area-inset-right, 0px);
    width: calc(var(--pitch) * 2.5); /* lugar para la borla rotada */
    contain: layout;
    pointer-events: none; /* los gestos los captura la superficie */
}

/* Paletas de materiales (Etapa 8). --bead-texture (imagen propia) las tapa. */
.mala-column[data-material='wood'] {
    --bead-hi: #d3a878;
    --bead-mid: #9a6b42;
    --bead-lo: #5e3a21;
}

.mala-column[data-material='bodhi'] {
    --bead-hi: #f2e7cd;
    --bead-mid: #cdb289;
    --bead-lo: #8a6f47;
}

.mala-column[data-material='red'] {
    --bead-hi: #e8907e;
    --bead-mid: #b93a2b;
    --bead-lo: #711b10;
}

.mala-column[data-material='blue'] {
    --bead-hi: #93bbe9;
    --bead-mid: #3b6fb5;
    --bead-lo: #1c3d6b;
}

.mala-string {
    position: absolute;
    top: 0;
    bottom: 0;
    right: calc(var(--pitch) * 0.5 - 1px);
    width: 2px;
    background: linear-gradient(
        to bottom,
        transparent,
        var(--bead-lo) 8%,
        var(--bead-lo) 92%,
        transparent
    );
    opacity: 0.55;
}

.mala-container {
    position: absolute;
    inset: 0;
    will-change: transform;
}

.mala-node {
    position: absolute;
    right: 0;
    width: var(--pitch);
    height: 0;
}
</style>
