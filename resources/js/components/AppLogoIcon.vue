<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- La cuenta del mala: el logo de mantreando (mismo diseño que el
         ícono PWA). Colores propios — funciona sobre claro y oscuro. -->
    <svg
        v-bind="$attrs"
        :class="className"
        viewBox="0 0 48 48"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <defs>
            <radialGradient id="mantreando-bead" cx="38%" cy="32%" r="78%">
                <stop offset="0%" stop-color="#d3a878" />
                <stop offset="55%" stop-color="#9a6b42" />
                <stop offset="100%" stop-color="#5e3a21" />
            </radialGradient>
        </defs>
        <!-- hilo -->
        <rect
            x="22.9"
            y="0"
            width="2.2"
            height="48"
            rx="1.1"
            fill="#8a6f47"
            opacity="0.65"
        />
        <!-- cuenta -->
        <circle cx="24" cy="24" r="15.5" fill="url(#mantreando-bead)" />
    </svg>
</template>
