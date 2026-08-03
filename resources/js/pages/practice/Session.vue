<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import MalaStrand from '@/components/mala/MalaStrand.vue';
import { useMala } from '@/composables/useMala';
import { db } from '@/lib/practice/db';
import { getLocalDate } from '@/lib/practice/localDate';
import { SessionRecorder } from '@/lib/practice/recorder';
import { syncAll } from '@/lib/practice/sync';
import type {
    ActiveSessionState,
    CachedMalaPreset,
    CachedMantra,
    CachedToday,
    CachedUser,
    PracticeMode,
} from '@/lib/practice/types';

/**
 * Pantalla de práctica real (Etapa 5). Isla offline: el único dato del
 * servidor es el ID del mantra; todo lo demás sale de IndexedDB. Sin
 * distracciones: mala + texto del mantra + contador discreto + salir.
 */

const props = defineProps<{ mantraId: number }>();

const mantra = ref<CachedMantra | null>(null);
const loading = ref(true);
const missing = ref(false);
const resumeCandidate = ref<ActiveSessionState | null>(null);
const celebration = ref<'mala' | 'commitment' | 'goal' | null>(null);
const exiting = ref(false);

let timezone: string | undefined;
let todayBase = 0; // recitaciones de HOY previas a esta sesión (este mantra)
let totalBase = 0; // recitaciones históricas previas (objetivo total)
let commitmentCelebrated = false;
let goalCelebrated = false;
let celebrationTimer: ReturnType<typeof setTimeout> | undefined;

const recorder = new SessionRecorder();
const mala = useMala('traditional');

const dailyCommitment = computed(
    () => mantra.value?.pivot.daily_commitment ?? null,
);
const dailyProgress = computed(
    () => todayBase + mala.snapshot.value.totalCount,
);
const totalGoal = computed(() => mantra.value?.pivot.total_goal ?? null);
const totalProgress = computed(
    () => totalBase + mala.snapshot.value.totalCount,
);

function celebrate(kind: 'mala' | 'commitment' | 'goal'): void {
    celebration.value = kind;
    clearTimeout(celebrationTimer);
    celebrationTimer = setTimeout(() => (celebration.value = null), 3500);
}

function startSession(resume: ActiveSessionState | null): void {
    const settings = userSettings.value;
    const mode: PracticeMode =
        resume?.mode ??
        ((settings?.default_mode as PracticeMode | undefined) ?? 'traditional');

    if (resume) {
        mala.restore({
            mode: resume.mode,
            count: resume.count,
            round: resume.round,
            totalCount: resume.totalCount,
            direction: resume.direction,
            position: resume.position,
        });
    } else if (mode !== mala.mode.value) {
        mala.setMode(mode);
    }

    void recorder.begin({ mantraId: props.mantraId, mode, resume });
    resumeCandidate.value = null;
}

async function resolveResume(action: 'continue' | 'finish-and-restart') {
    const candidate = resumeCandidate.value;

    if (!candidate) {
        return;
    }

    if (action === 'continue') {
        startSession(candidate);

        return;
    }

    // Guardar lo acumulado como sesión y arrancar limpia.
    await recorder.begin({
        mantraId: props.mantraId,
        mode: candidate.mode,
        resume: candidate,
    });
    await recorder.finish(timezone);
    todayBase += candidate.totalCount;
    void syncAll();
    startSession(null);
}

async function exit(): Promise<void> {
    if (exiting.value) {
        return;
    }

    exiting.value = true;
    await recorder.finish(timezone);
    void syncAll();
    router.visit('/practice');
}

const userSettings = ref<Record<string, unknown> | null>(null);
const preset = ref<CachedMalaPreset>({ material: 'wood', texture_url: null });

onMounted(async () => {
    const [mantraRow, userMeta, todayMeta, totalsMeta, presetMeta] =
        await Promise.all([
            db.mantras.get(props.mantraId),
            db.meta.get('user'),
            db.meta.get('today'),
            db.meta.get('totals'),
            db.meta.get('malaPreset'),
        ]);

    if (presetMeta?.value) {
        preset.value = presetMeta.value as CachedMalaPreset;
    }

    if (!mantraRow) {
        // Cache vacía (primer uso online): intentar poblarla una vez.
        await syncAll();
        mantra.value = (await db.mantras.get(props.mantraId)) ?? null;
    } else {
        mantra.value = mantraRow;
    }

    loading.value = false;

    if (!mantra.value) {
        missing.value = true;

        return;
    }

    const user = userMeta?.value as CachedUser | undefined;
    timezone = user?.timezone ?? undefined;
    userSettings.value = (user?.settings as Record<string, unknown>) ?? null;

    mala.applyFeedbackPrefs({
        haptics: (userSettings.value?.haptics_enabled as boolean) ?? true,
        sound: (userSettings.value?.sound_enabled as boolean) ?? false,
    });

    // Progreso de hoy (si la cache es de hoy en la tz del usuario)
    const today = todayMeta?.value as CachedToday | undefined;

    if (today && today.local_date === getLocalDate(timezone)) {
        todayBase = today.by_mantra[String(props.mantraId)] ?? 0;
    }

    // Progreso histórico (objetivo total)
    const totals = totalsMeta?.value as
        | { by_mantra: Record<string, number> }
        | undefined;
    totalBase = totals?.by_mantra[String(props.mantraId)] ?? 0;
    goalCelebrated =
        totalGoal.value !== null && totalBase >= totalGoal.value;

    // ¿Sesión interrumpida de este mantra?
    const active = await SessionRecorder.findActive(props.mantraId);

    if (active && active.totalCount > 0) {
        resumeCandidate.value = active;
    } else {
        startSession(null);
    }

    // Recorder + felicitaciones sobrias
    mala.subscribe((event) => {
        recorder.update(
            {
                count: mala.snapshot.value.count,
                round: mala.snapshot.value.round,
                totalCount: mala.snapshot.value.totalCount,
                direction: mala.snapshot.value.direction,
                position: mala.snapshot.value.position,
            },
            { force: event.type === 'completed' },
        );

        if (event.type === 'completed') {
            celebrate('mala');
        }

        if (event.type === 'bead') {
            if (
                !commitmentCelebrated &&
                dailyCommitment.value !== null &&
                dailyProgress.value >= dailyCommitment.value
            ) {
                commitmentCelebrated = true;
                celebrate('commitment');
            }

            if (
                !goalCelebrated &&
                totalGoal.value !== null &&
                totalProgress.value >= totalGoal.value
            ) {
                goalCelebrated = true;
                celebrate('goal');
            }
        }
    });
});

onUnmounted(() => {
    clearTimeout(celebrationTimer);
    void recorder.flush();
});
</script>

<template>
    <Head :title="mantra ? `Práctica — ${mantra.name}` : 'Práctica'" />

    <div class="min-h-dvh bg-background text-foreground">
        <template v-if="missing">
            <div
                class="flex min-h-dvh flex-col items-center justify-center gap-3 p-6 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    No se encontró este mantra en la memoria local. Conectate
                    una vez para descargar tu biblioteca.
                </p>
                <button
                    type="button"
                    class="text-sm underline underline-offset-4"
                    @click="router.visit('/practice')"
                >
                    Volver a Práctica
                </button>
            </div>
        </template>

        <template v-else-if="!loading && mantra">
            <MalaStrand
                :pool="mala.pool"
                :material="preset.material"
                :texture-url="preset.texture_url"
                :set-container="mala.setContainer"
                :set-column="mala.setColumn"
                :on-pointer-down="mala.onPointerDown"
                :on-pointer-move="mala.onPointerMove"
                :on-pointer-up="mala.onPointerUp"
            />

            <!-- Contenido sereno a la izquierda; la hebra vive a la derecha -->
            <div
                class="pointer-events-none relative z-10 flex h-dvh max-w-[62vw] flex-col p-5"
            >
                <header class="pointer-events-auto">
                    <button
                        type="button"
                        class="-m-2 rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground"
                        aria-label="Terminar la práctica y salir"
                        @click="exit"
                    >
                        <X class="size-5" />
                    </button>
                </header>

                <main class="flex flex-1 flex-col justify-center gap-6">
                    <div>
                        <p
                            class="text-xs tracking-widest text-muted-foreground uppercase"
                        >
                            {{ mantra.name }}
                        </p>
                        <p class="mt-3 text-2xl leading-relaxed font-light">
                            {{ mantra.text }}
                        </p>
                        <p
                            v-if="mantra.transliteration"
                            class="mt-2 text-sm text-muted-foreground italic"
                        >
                            {{ mantra.transliteration }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="font-mono text-6xl font-extralight tabular-nums"
                            aria-live="polite"
                        >
                            {{ mala.snapshot.value.count }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <template v-if="mala.snapshot.value.round > 0">
                                {{ mala.snapshot.value.round }}
                                {{
                                    mala.snapshot.value.round === 1
                                        ? 'mala completo'
                                        : 'malas completos'
                                }}
                                ·
                            </template>
                            {{ mala.snapshot.value.totalCount }} en esta sesión
                        </p>
                        <p
                            v-if="dailyCommitment"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ Math.min(dailyProgress, dailyCommitment) }} /
                            {{ dailyCommitment }} de tu compromiso diario
                        </p>
                    </div>
                </main>
            </div>

            <!-- Recuperación de sesión interrumpida -->
            <div
                v-if="resumeCandidate"
                class="fixed inset-0 z-20 flex items-center justify-center bg-background/85 p-6 backdrop-blur-sm"
            >
                <div class="w-full max-w-sm space-y-4 text-center">
                    <p class="text-sm text-muted-foreground">
                        Tenés una práctica sin terminar con este mantra:
                        <span class="font-medium text-foreground">
                            {{ resumeCandidate.totalCount }} recitaciones
                        </span>
                    </p>
                    <div class="flex flex-col gap-2">
                        <button
                            type="button"
                            class="rounded-md border bg-foreground px-4 py-2.5 text-sm font-medium text-background"
                            @click="resolveResume('continue')"
                        >
                            Continuar donde quedé
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-4 py-2.5 text-sm"
                            @click="resolveResume('finish-and-restart')"
                        >
                            Guardarla y empezar de nuevo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Felicitación sobria -->
            <Transition
                enter-active-class="transition-opacity duration-500"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-500"
                leave-to-class="opacity-0"
            >
                <button
                    v-if="celebration"
                    type="button"
                    class="fixed inset-0 z-20 flex cursor-default flex-col items-center justify-center gap-2 bg-background/70 backdrop-blur-[2px]"
                    @click="celebration = null"
                >
                    <span class="h-px w-10 bg-foreground/30" />
                    <p class="text-lg font-light">
                        {{
                            celebration === 'mala'
                                ? 'Completaste un mala'
                                : celebration === 'commitment'
                                  ? 'Alcanzaste tu compromiso diario'
                                  : 'Alcanzaste tu objetivo total'
                        }}
                    </p>
                    <span class="h-px w-10 bg-foreground/30" />
                </button>
            </Transition>
        </template>
    </div>
</template>
