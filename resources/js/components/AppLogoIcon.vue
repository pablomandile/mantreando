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
    <!-- El logo de mantreando: el mala con la flor de loto (mismo archivo
         que el ícono de la PWA y el favicon, en public/img/logo.webp). Ya
         trae su propio fondo y esquinas redondeadas, así que no hace falta
         ningún dibujo a mano como el bead SVG que tenía antes. -->
    <img
        v-bind="$attrs"
        :class="className"
        src="/img/logo.webp"
        alt=""
        aria-hidden="true"
    />
</template>
