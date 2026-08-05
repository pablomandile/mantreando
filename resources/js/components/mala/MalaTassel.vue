<script setup lang="ts">
/**
 * Borla (tassel) tradicional que cuelga de la cuenta gurú, rotada hacia la
 * izquierda de la pantalla (espacio libre) para no chocar con la hebra.
 *
 * Tres partes, como una borla de hilo de verdad: la cabeza (el ovillo del
 * remate), el atado que la ciñe y la falda de flecos que se abre al caer.
 *
 * Los flecos se generan en vez de escribirse a mano: son 19 y cada uno lleva
 * su propia curva, grosor y opacidad. Dibujados uno por uno el archivo era
 * ilegible y subir la densidad significaba reescribirlos todos.
 *
 * El tamaño NO cambió al densificar: la columna del mala reserva 2.5 pitch de
 * ancho y el texto del mantra ahora llega hasta ~2 pitch del borde, así que
 * una borla más grande se le metería encima. Más flecos, mismo envoltorio.
 */

const STRAND_COUNT = 19;

/** Ancho del viewBox; el centro (20) es el eje de la borla. */
const CENTER = 20;
const NECK_Y = 22; // donde termina el atado y arrancan los flecos
const TIP_Y = 96; // largo de la falda

const strands = Array.from({ length: STRAND_COUNT }, (_, index) => {
    // −1 (izquierda) .. 1 (derecha) respecto del eje.
    const spread = (index / (STRAND_COUNT - 1)) * 2 - 1;
    const fromEdge = Math.abs(spread);

    // Arrancan casi juntos bajo el atado y se abren al caer: los de los bordes
    // caen más cortos, como el hilo real cuando la falda se despliega.
    const startX = CENTER + spread * 4.5;
    const endX = CENTER + spread * 15;
    const endY = TIP_Y - fromEdge * fromEdge * 12;
    const bendX = CENTER + spread * 9;

    return {
        d: `M${startX.toFixed(1)} ${NECK_Y} C${bendX.toFixed(1)} ${NECK_Y + 26} ${endX.toFixed(1)} ${(endY - 22).toFixed(1)} ${endX.toFixed(1)} ${endY.toFixed(1)}`,
        // Los del centro, apenas más gruesos y opacos: leen como los que están
        // adelante y le dan volumen a la falda sin dibujar sombras.
        width: (1.7 - fromEdge * 0.45).toFixed(2),
        opacity: (1 - fromEdge * 0.25).toFixed(2),
    };
});
</script>

<template>
    <svg
        class="mala-tassel"
        viewBox="0 0 40 100"
        fill="none"
        aria-hidden="true"
    >
        <!-- Cabeza: el ovillo del remate, con un realce arriba a la izquierda
             para que no se lea como un círculo plano. -->
        <ellipse cx="20" cy="11" rx="8.5" ry="9.5" class="head" />
        <ellipse cx="17" cy="7.5" rx="4" ry="4.5" class="head-sheen" />

        <!-- Atado: las vueltas de hilo que ciñen la cabeza contra la falda. -->
        <path
            d="M11.5 19 Q20 22.5 28.5 19 L28.5 22 Q20 25.5 11.5 22 Z"
            class="wrap"
        />
        <path d="M12.5 22.6 Q20 25.6 27.5 22.6" class="wrap-line" />

        <!-- Falda -->
        <path
            v-for="strand in strands"
            :key="strand.d"
            :d="strand.d"
            class="strand"
            :stroke-width="strand.width"
            :stroke-opacity="strand.opacity"
        />
    </svg>
</template>

<style scoped>
.mala-tassel {
    /* Color propio de la borla (Mi mala); sin elegir, sigue al material de las
       cuentas como hasta ahora. */
    --tassel-ink: var(--tassel-color, var(--bead-lo));

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

.head {
    fill: var(--tassel-ink);
    stroke: color-mix(in srgb, var(--tassel-ink) 55%, black);
    stroke-width: 0.8;
}

.head-sheen {
    fill: rgb(255 255 255 / 0.18);
}

.wrap {
    fill: color-mix(in srgb, var(--tassel-ink) 78%, black);
}

.wrap-line {
    stroke: rgb(255 255 255 / 0.16);
    stroke-width: 0.9;
    fill: none;
}

.strand {
    stroke: var(--tassel-ink);
    stroke-linecap: round;
    fill: none;
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
