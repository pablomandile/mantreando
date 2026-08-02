<script setup lang="ts">
/**
 * Borla (tassel) tradicional que cuelga de la cuenta gurú, rotada hacia la
 * izquierda de la pantalla (espacio libre) para no chocar con la hebra.
 * SVG inline: capuchón + 7 hebras. El vaivén se apaga con reduced-motion.
 */
</script>

<template>
    <svg
        class="mala-tassel"
        viewBox="0 0 40 100"
        fill="none"
        aria-hidden="true"
    >
        <!-- capuchón -->
        <path
            d="M12 2 H28 L26 18 Q20 22 14 18 Z"
            fill="var(--bead-lo)"
            stroke="color-mix(in srgb, var(--bead-lo) 60%, black)"
            stroke-width="1"
        />
        <!-- 7 hebras -->
        <path d="M14 19 C12 50 8 70 6 96" class="strand" />
        <path d="M16 20 C15 52 12 72 11 97" class="strand" />
        <path d="M18 21 C18 54 16 74 16 98" class="strand" />
        <path d="M20 21 C20 55 20 76 20 99" class="strand" />
        <path d="M22 21 C22 54 24 74 24 98" class="strand" />
        <path d="M24 20 C25 52 28 72 29 97" class="strand" />
        <path d="M26 19 C28 50 32 70 34 96" class="strand" />
    </svg>
</template>

<style scoped>
.mala-tassel {
    position: absolute;
    top: 78%;
    left: 50%;
    width: calc(var(--pitch) * 0.62);
    height: calc(var(--pitch) * 1.55);
    transform: translateX(-50%) rotate(30deg);
    transform-origin: top center;
    animation: tassel-sway 4.5s ease-in-out infinite alternate;
    pointer-events: none;
}

.strand {
    stroke: var(--bead-lo);
    stroke-width: 1.6;
    stroke-linecap: round;
}

@keyframes tassel-sway {
    from {
        transform: translateX(-50%) rotate(27deg);
    }

    to {
        transform: translateX(-50%) rotate(33deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .mala-tassel {
        animation: none;
    }
}
</style>
