<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MalaStrand from '@/components/mala/MalaStrand.vue';
import { useMala } from '@/composables/useMala';

/**
 * SPIKE técnico del mala (Etapa 2): valida render virtualizado, swipe con
 * inercia, cuenta gurú con rebote y 60 fps. El HUD de la izquierda es
 * descartable — la pantalla real de práctica llega en la Etapa 5.
 * Página fullscreen (sin layout) y sin datos del servidor (isla).
 */

const {
    snapshot,
    pool,
    mode,
    haptics,
    sound,
    hapticsSupported,
    fps,
    droppedFrames,
    setContainer,
    setColumn,
    setMode,
    reset,
    toggleHaptics,
    toggleSound,
    onPointerDown,
    onPointerMove,
    onPointerUp,
} = useMala('traditional');
</script>

<template>
    <Head title="Mala — spike" />

    <div class="min-h-dvh bg-background text-foreground">
        <MalaStrand
            :pool="pool"
            material="wood"
            :set-container="setContainer"
            :set-column="setColumn"
            :on-pointer-down="onPointerDown"
            :on-pointer-move="onPointerMove"
            :on-pointer-up="onPointerUp"
        />

        <!-- HUD de debug (z-index sobre la superficie de gesto) -->
        <aside
            class="pointer-events-none relative z-10 flex h-dvh max-w-[55vw] flex-col justify-between p-4"
        >
            <div class="pointer-events-auto space-y-1 text-sm">
                <Link
                    href="/practice"
                    class="text-xs text-muted-foreground underline underline-offset-4"
                >
                    ← Volver a Práctica
                </Link>

                <p class="pt-2 font-mono text-4xl font-semibold tabular-nums">
                    {{ snapshot.count }}
                </p>
                <p class="text-xs text-muted-foreground">
                    vuelta {{ snapshot.round }} · total
                    {{ snapshot.totalCount }} ·
                    {{ snapshot.direction === 1 ? '↑' : '↓' }}
                </p>
                <p class="font-mono text-xs text-muted-foreground">
                    pos {{ snapshot.position.toFixed(2) }} · slot
                    {{ snapshot.restSlot }}
                </p>
            </div>

            <div class="pointer-events-auto space-y-2 pb-2 text-xs">
                <p class="font-mono text-muted-foreground">
                    {{ fps }} fps · {{ droppedFrames }} frames >25ms
                </p>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5"
                        :class="{ 'bg-accent': mode === 'traditional' }"
                        @click="setMode('traditional')"
                    >
                        Tradicional
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5"
                        :class="{ 'bg-accent': mode === 'assisted' }"
                        @click="setMode('assisted')"
                    >
                        Asistido
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="hapticsSupported"
                        type="button"
                        class="rounded-md border px-3 py-1.5"
                        :class="{ 'bg-accent': haptics }"
                        @click="toggleHaptics"
                    >
                        Vibración {{ haptics ? 'on' : 'off' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5"
                        :class="{ 'bg-accent': sound }"
                        @click="toggleSound"
                    >
                        Sonido {{ sound ? 'on' : 'off' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5"
                        @click="reset"
                    >
                        Reset
                    </button>
                </div>

                <p class="max-w-56 text-muted-foreground">
                    Deslizá verticalmente sobre la pantalla.
                    {{
                        mode === 'traditional'
                            ? 'Una cuenta por gesto; al llegar al gurú, empujá firme para invertir.'
                            : 'Deslizá con impulso o tocá para avanzar.'
                    }}
                </p>
            </div>
        </aside>
    </div>
</template>
