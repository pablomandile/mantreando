<script setup lang="ts">
/**
 * Serpentina de cintas cayendo, para cuando se completa la última etapa del
 * retiro. Sin librería: son puros <span> posicionados al azar con CSS puro,
 * como los íconos del ábaco — un festejo así no justifica una dependencia
 * nueva.
 *
 * Se dispara cambiando `active` de false a true (un watch en la página).
 * Genera las cintas UNA vez por disparo, no en cada render, y se limpia
 * sola pasados unos segundos.
 */
import { ref, watch } from 'vue';

const props = defineProps<{ active: boolean }>();

const COLORS = [
    '#c2902f', // ámbar
    '#4f8a55', // verde
    '#3b6fb5', // azul
    '#b93a2b', // rojo
    '#7a4f96', // violeta
    '#c26a2c', // naranja
];
const PIECE_COUNT = 60;
const DURATION_MS = 3200;

interface Piece {
    id: number;
    left: number;
    color: string;
    delay: number;
    duration: number;
    drift: number;
    rotate: number;
}

const pieces = ref<Piece[]>([]);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function launch(): void {
    pieces.value = Array.from({ length: PIECE_COUNT }, (_, id) => ({
        id,
        left: Math.random() * 100,
        color: COLORS[id % COLORS.length],
        delay: Math.random() * 0.4,
        duration: 2.2 + Math.random() * 1.2,
        drift: (Math.random() - 0.5) * 120,
        rotate: 360 + Math.random() * 720,
    }));

    if (hideTimer !== null) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        pieces.value = [];
    }, DURATION_MS);
}

watch(
    () => props.active,
    (value) => {
        if (value) {
            launch();
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="pieces.length > 0"
            class="pointer-events-none fixed inset-0 z-50 overflow-hidden"
            aria-hidden="true"
        >
            <span
                v-for="piece in pieces"
                :key="piece.id"
                class="confetti-piece"
                :style="{
                    left: `${piece.left}%`,
                    backgroundColor: piece.color,
                    animationDelay: `${piece.delay}s`,
                    animationDuration: `${piece.duration}s`,
                    '--drift': `${piece.drift}px`,
                    '--rotate': `${piece.rotate}deg`,
                }"
            />
        </div>
    </Teleport>
</template>

<style scoped>
.confetti-piece {
    position: absolute;
    top: -5%;
    width: 0.5rem;
    height: 1.1rem;
    border-radius: 1px;
    opacity: 0.9;
    animation-name: confetti-fall;
    animation-timing-function: ease-in;
    animation-fill-mode: forwards;
}

@keyframes confetti-fall {
    from {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0.9;
    }

    to {
        transform: translateY(110vh) translateX(var(--drift))
            rotate(var(--rotate));
        opacity: 0.4;
    }
}

@media (prefers-reduced-motion: reduce) {
    .confetti-piece {
        animation: none;
        display: none;
    }
}
</style>
