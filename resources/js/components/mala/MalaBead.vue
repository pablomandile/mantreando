<script setup lang="ts">
import MalaTassel from '@/components/mala/MalaTassel.vue';

/**
 * Una cuenta del mala. El material se resuelve por CSS custom properties
 * (--bead-hi/mid/lo) scoped por data-material en la columna: ese es TODO
 * el hook de theming que necesita la Etapa 8.
 */
defineProps<{
    isGuru: boolean;
    active: boolean;
}>();
</script>

<template>
    <div class="mala-bead" :data-guru="isGuru || undefined" :data-active="active || undefined">
        <MalaTassel v-if="isGuru" />
    </div>
</template>

<style scoped>
.mala-bead {
    position: absolute;
    left: 50%;
    top: 0;
    width: calc(var(--pitch) * 0.82);
    height: calc(var(--pitch) * 0.82);
    transform: translate(-50%, -50%);
    border-radius: 50%;
    /* Capa 1: sombreado esférico (siempre). Capa 2: textura propia del
       usuario, o el gradiente del material como fallback. */
    background-image:
        radial-gradient(
            circle at 35% 30%,
            rgb(255 255 255 / 0.3),
            transparent 48%,
            rgb(0 0 0 / 0.28) 100%
        ),
        var(
            --bead-texture,
            radial-gradient(
                circle at 35% 30%,
                var(--bead-hi),
                var(--bead-mid) 55%,
                var(--bead-lo) 100%
            )
        );
    background-size: cover;
    background-position: center;
    box-shadow:
        0 1px 3px rgb(0 0 0 / 0.35),
        inset 0 -2px 4px rgb(0 0 0 / 0.2);
    transition: scale 150ms ease-out;
}

.mala-bead[data-active] {
    scale: 1.12;
    box-shadow:
        0 1px 4px rgb(0 0 0 / 0.4),
        inset 0 -2px 4px rgb(0 0 0 / 0.2),
        0 0 0 2px rgb(255 255 255 / 0.25);
}

.mala-bead[data-guru] {
    width: calc(var(--pitch) * 1.148); /* 0.82 × 1.4 */
    height: calc(var(--pitch) * 1.148);
    /* El gurú siempre lleva un velo más profundo, con o sin textura. */
    background-image:
        radial-gradient(
            circle at 35% 30%,
            rgb(255 255 255 / 0.15),
            rgb(0 0 0 / 0.2) 55%,
            rgb(0 0 0 / 0.5) 100%
        ),
        var(
            --bead-texture,
            radial-gradient(
                circle at 35% 30%,
                var(--bead-mid),
                var(--bead-lo) 60%,
                color-mix(in srgb, var(--bead-lo) 55%, black) 100%
            )
        );
}

@media (prefers-reduced-motion: reduce) {
    .mala-bead {
        transition: none;
    }
}
</style>
