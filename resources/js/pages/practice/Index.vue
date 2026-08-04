<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import MalaStrand from '@/components/mala/MalaStrand.vue';
import { useLiveQuery } from '@/composables/useLiveQuery';
import { useMala } from '@/composables/useMala';
import { usePracticeSync } from '@/composables/usePracticeSync';
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
 * LA pantalla de práctica: el mala aparece al entrar a la app.
 * Select de mantra arriba; con mantra elegido, switch "Seguir objetivo"
 * (default, con la meta configurada en el panel Objetivo) o "Cuenta libre".
 * Isla offline: todos los datos salen de IndexedDB.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Práctica', href: '/practice' }],
    },
});

usePracticeSync();

const mantras = useLiveQuery<CachedMantra[]>(
    () => db.mantras.orderBy('sort').toArray(),
    [],
);
const outboxCount = useLiveQuery(() => db.outbox.count(), 0);

const selectedId = ref<number | null>(null);
const mantra = computed(
    () => mantras.value.find((m) => m.id === selectedId.value) ?? null,
);

/**
 * La columna de la práctica tiene que entrar entera en pantalla: imagen,
 * texto y contador. Los mantras largos (Amitayus, Vajrasatva largo) la
 * llenan ellos solos y empujaban el contador fuera de la vista, así que
 * bajan un escalón de letra, interlineado e imagen. Los cortos, que son la
 * mayoría, se ven igual que antes.
 */
const mantraLength = computed(() => {
    const length = mantra.value?.text.length ?? 0;

    return length > 250 ? 'long' : length > 120 ? 'medium' : 'short';
});

// Umbrales y tamaños medidos contra los 19 mantras del sistema en 412x915:
// con estos valores ninguno estira la pantalla. Los dos casos que la
// forzaban son Amitayus (296 caracteres) y Vajrasatva largo (318).
const mantraTextClass = computed(
    () =>
        ({
            long: 'text-[0.8rem] leading-snug sm:text-sm',
            medium: 'text-base leading-snug sm:text-lg',
            short: 'text-lg leading-relaxed sm:text-xl',
        })[mantraLength.value],
);

// ── Objetivo diario (panel Objetivo) ────────────────────────────────────────
const DEFAULT_DAILY_GOAL = 108;

// Settings reactivos desde la cache: si el sync trae un objetivo nuevo
// (recién guardado en el panel), la leyenda se actualiza sola.
const userMetaLive = useLiveQuery(() => db.meta.get('user'), undefined);
const liveSettings = computed(
    () =>
        ((userMetaLive.value?.value as CachedUser | undefined)?.settings ??
            {}) as Record<string, unknown>,
);
const dailyGoalValue = computed(() =>
    Number(liveSettings.value.daily_goal) > 0
        ? Number(liveSettings.value.daily_goal)
        : DEFAULT_DAILY_GOAL,
);
const globalGoalValue = computed(() =>
    Number(liveSettings.value.total_goal) > 0
        ? Number(liveSettings.value.total_goal)
        : null,
);

/** true = "Seguir objetivo" (default SIEMPRE al entrar); false = cuenta libre */
const followGoal = ref(true);

/** Progreso de HOY (todos los mantras) hacia la meta diaria. */
const dailyProgressToday = computed(() =>
    Math.min(
        todayTotalBaseReactive.value + mala.snapshot.value.totalCount,
        dailyGoalValue.value,
    ),
);

// ── Celebraciones sobrias ───────────────────────────────────────────────────
const celebration = ref<
    'mala' | 'commitment' | 'goal' | 'daily-goal' | 'global-goal' | null
>(null);
let celebrationTimer: ReturnType<typeof setTimeout> | undefined;
let commitmentCelebrated = false;
let totalGoalCelebrated = false;
let dailyGoalCelebrated = false;
let globalGoalCelebrated = false;

const celebrationText = computed(() => {
    switch (celebration.value) {
        case 'mala':
            return t('Completaste un mala');
        case 'commitment':
            return t('Alcanzaste tu compromiso diario');
        case 'goal':
            return t('Alcanzaste tu objetivo total');
        case 'daily-goal':
            return t('Alcanzaste tu objetivo diario de :goal recitaciones', {
                goal: String(dailyGoalValue.value),
            });
        case 'global-goal':
            return t('Alcanzaste tu objetivo global de :goal recitaciones', {
                goal: (globalGoalValue.value ?? 0).toLocaleString('es'),
            });
        default:
            return '';
    }
});

function celebrate(kind: NonNullable<typeof celebration.value>): void {
    celebration.value = kind;
    clearTimeout(celebrationTimer);
    celebrationTimer = setTimeout(() => (celebration.value = null), 3500);
}

// ── Mala + recorder ─────────────────────────────────────────────────────────
const recorder = new SessionRecorder();
const mala = useMala('traditional');
const preset = ref<CachedMalaPreset>({ material: 'wood', texture_url: null });
const resumeCandidate = ref<ActiveSessionState | null>(null);

let timezone: string | undefined;
let defaultMode: PracticeMode = 'traditional';
let todayByMantra: Record<string, number> = {};
let todayLocalDate = '';
let totalsByMantra: Record<string, number> = {};
let todayBase = 0;
let totalBase = 0;

// Bases acumuladas de las metas globales (panel Objetivo)
const todayTotalBaseReactive = ref(0); // recitaciones de HOY (todos los mantras) previas
let allTimeBase = 0; // recitaciones históricas (todos los mantras) previas

const dailyCommitment = computed(
    () => mantra.value?.pivot.daily_commitment ?? null,
);
const totalGoal = computed(() => mantra.value?.pivot.total_goal ?? null);

function resetPerSessionFlags(): void {
    commitmentCelebrated = false;
    followGoal.value = true; // objetivo por default, siempre
}

async function startSession(
    mantraId: number,
    resume: ActiveSessionState | null,
): Promise<void> {
    resetPerSessionFlags();

    todayBase =
        todayLocalDate === getLocalDate(timezone)
            ? (todayByMantra[String(mantraId)] ?? 0)
            : 0;
    totalBase = totalsByMantra[String(mantraId)] ?? 0;
    totalGoalCelebrated =
        totalGoal.value !== null && totalBase >= (totalGoal.value ?? 0);

    if (resume) {
        mala.restore({
            mode: resume.mode,
            count: resume.count,
            round: resume.round,
            totalCount: resume.totalCount,
            direction: resume.direction,
            position: resume.position,
        });
    } else {
        mala.setMode(defaultMode);
    }

    await recorder.begin({ mantraId, mode: resume?.mode ?? defaultMode, resume });
    await db.meta.put({ key: 'lastMantraId', value: mantraId });
}

/** Cierra la sesión en curso (encola si hubo cuentas) y arranca otra. */
async function switchMantra(mantraId: number): Promise<void> {
    const state = recorder.getState();

    if (state !== null) {
        // Lo contado pasa a las bases: las metas diaria/global siguen
        // acumulando a través de los cambios de mantra.
        todayTotalBaseReactive.value += state.totalCount;
        allTimeBase += state.totalCount;
        todayByMantra[String(state.mantra_id)] =
            (todayByMantra[String(state.mantra_id)] ?? 0) + state.totalCount;
        await recorder.finish(timezone);
        void syncAll();
    }

    selectedId.value = mantraId;
    await startSession(mantraId, null);
}

function onMantraChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (value) {
        void switchMantra(Number(value));
    }
}

async function resolveResume(action: 'continue' | 'finish-and-restart') {
    const candidate = resumeCandidate.value;

    if (!candidate) {
        return;
    }

    resumeCandidate.value = null;

    if (action === 'continue') {
        await startSession(candidate.mantra_id, { ...candidate });

        return;
    }

    await recorder.begin({
        mantraId: candidate.mantra_id,
        mode: candidate.mode,
        resume: { ...candidate },
    });
    await recorder.finish(timezone);
    todayByMantra[String(candidate.mantra_id)] =
        (todayByMantra[String(candidate.mantra_id)] ?? 0) +
        candidate.totalCount;
    todayTotalBaseReactive.value += candidate.totalCount;
    allTimeBase += candidate.totalCount;
    void syncAll();
    await startSession(candidate.mantra_id, null);
}

onMounted(async () => {
    const [userMeta, todayMeta, totalsMeta, presetMeta, lastMeta] =
        await Promise.all([
            db.meta.get('user'),
            db.meta.get('today'),
            db.meta.get('totals'),
            db.meta.get('malaPreset'),
            db.meta.get('lastMantraId'),
        ]);

    const user = userMeta?.value as CachedUser | undefined;
    timezone = user?.timezone ?? undefined;
    const settings = (user?.settings ?? {}) as Record<string, unknown>;
    defaultMode = (settings.default_mode as PracticeMode) ?? 'traditional';

    mala.applyFeedbackPrefs({
        haptics: (settings.haptics_enabled as boolean) ?? true,
        sound: (settings.sound_enabled as boolean) ?? false,
    });

    // (daily_goal y total_goal se leen reactivos vía liveSettings)

    if (presetMeta?.value) {
        preset.value = presetMeta.value as CachedMalaPreset;
    }

    const today = todayMeta?.value as CachedToday | undefined;
    todayByMantra = today?.by_mantra ?? {};
    todayLocalDate = today?.local_date ?? '';
    totalsByMantra =
        (totalsMeta?.value as { by_mantra: Record<string, number> })
            ?.by_mantra ?? {};

    // Bases de las metas globales
    todayTotalBaseReactive.value =
        today && today.local_date === getLocalDate(timezone) ? today.total : 0;
    allTimeBase = Object.values(totalsByMantra).reduce((a, b) => a + b, 0);
    dailyGoalCelebrated = todayTotalBaseReactive.value >= dailyGoalValue.value;
    globalGoalCelebrated =
        globalGoalValue.value !== null && allTimeBase >= globalGoalValue.value;

    // Prioridad de selección: sesión interrumpida → ?mantra=ID → último usado
    const active = await SessionRecorder.findAnyActive();

    if (active && active.totalCount > 0) {
        selectedId.value = active.mantra_id;
        resumeCandidate.value = active;
    } else {
        const fromUrl = Number(
            new URLSearchParams(window.location.search).get('mantra'),
        );
        const lastUsed = Number(lastMeta?.value ?? 0);
        const candidate = fromUrl || lastUsed;

        if (candidate > 0) {
            selectedId.value = candidate;
            await startSession(candidate, null);
        }
    }

    // Recorder + celebraciones
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

        const total = mala.snapshot.value.totalCount;

        if (event.type === 'bead' || event.type === 'completed') {
            // La meta diaria del panel Objetivo manda (si el switch
            // "Seguir objetivo" está activo)
            if (
                followGoal.value &&
                !dailyGoalCelebrated &&
                todayTotalBaseReactive.value + total >= dailyGoalValue.value
            ) {
                dailyGoalCelebrated = true;
                celebrate('daily-goal');

                return;
            }
        }

        if (event.type === 'completed' && celebration.value === null) {
            celebrate('mala');
        }

        if (event.type === 'bead') {
            if (
                !commitmentCelebrated &&
                dailyCommitment.value !== null &&
                todayBase + total >= dailyCommitment.value
            ) {
                commitmentCelebrated = true;

                if (celebration.value === null) {
                    celebrate('commitment');
                }
            }

            if (
                !totalGoalCelebrated &&
                totalGoal.value !== null &&
                totalBase + total >= totalGoal.value
            ) {
                totalGoalCelebrated = true;

                if (celebration.value === null) {
                    celebrate('goal');
                }
            }

            // Meta global de por vida (siempre, es un hito)
            if (
                !globalGoalCelebrated &&
                globalGoalValue.value !== null &&
                allTimeBase + total >= globalGoalValue.value
            ) {
                globalGoalCelebrated = true;

                if (celebration.value === null) {
                    celebrate('global-goal');
                }
            }
        }
    });
});

onUnmounted(() => {
    clearTimeout(celebrationTimer);
    // Al navegar a otra sección se cierra y encola la sesión en curso.
    void recorder.finish(timezone).then((queued) => {
        if (queued) {
            void syncAll();
        }
    });
});
</script>

<template>
    <Head :title="t('Práctica')" />

    <!-- Contenedor de la práctica: llena el área bajo el header del panel -->
    <div class="relative min-h-0 flex-1 overflow-hidden">
        <!-- Velo con el color del mantra elegido (el mismo de su tarjeta).
             Primero en el DOM: queda detrás del mala y del texto. La opacidad
             hace el fondido al cambiar de mantra (background-image no anima). -->
        <div
            class="mantra-backdrop pointer-events-none absolute inset-0 transition-opacity duration-700"
            :data-color="mantra?.color ?? undefined"
            :class="mantra?.color ? 'opacity-100' : 'opacity-0'"
            aria-hidden="true"
        />
        <MalaStrand
            :pool="mala.pool"
            :material="preset.material"
            :texture-url="preset.texture_url"
            :aria-label="t('Mala: deslizá verticalmente para avanzar una cuenta')"
            :set-container="mala.setContainer"
            :set-column="mala.setColumn"
            :set-surface="mala.setSurface"
            :on-pointer-down="mala.onPointerDown"
            :on-pointer-move="mala.onPointerMove"
            :on-pointer-up="mala.onPointerUp"
        />

        <!-- Zona serena a la izquierda (sobre la superficie de gestos) -->
        <div
            class="pointer-events-none relative z-10 flex h-full max-w-[60%] flex-col gap-5 p-5 sm:max-w-sm"
        >
            <!-- Selector de mantra -->
            <div class="pointer-events-auto space-y-2">
                <select
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="t('Elegí un mantra')"
                    @change="onMantraChange"
                >
                    <option value="" disabled :selected="selectedId === null">
                        {{ t('Elegí un mantra') }}
                    </option>
                    <option
                        v-for="item in mantras"
                        :key="item.id"
                        :value="item.id"
                        :selected="item.id === selectedId"
                    >
                        {{ item.name }}
                    </option>
                </select>

                <!-- Objetivo o cuenta libre (con mantra elegido) -->
                <div
                    v-if="mantra"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="followGoal"
                        class="relative h-5 w-9 rounded-full transition-colors"
                        :class="followGoal ? 'bg-foreground' : 'bg-muted'"
                        @click="followGoal = !followGoal"
                    >
                        <span
                            class="absolute top-0.5 left-0.5 size-4 rounded-full bg-background shadow transition-transform"
                            :class="{ 'translate-x-4': followGoal }"
                        />
                    </button>
                    <span>
                        {{
                            followGoal
                                ? t('Seguir objetivo: :goal por día', {
                                      goal: String(dailyGoalValue),
                                  })
                                : t('Cuenta libre')
                        }}
                    </span>
                </div>

                <span
                    v-if="outboxCount > 0"
                    class="inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                >
                    {{ t(':count sin sincronizar', { count: String(outboxCount) }) }}
                </span>
            </div>

            <!-- Imagen del buda: mismo ancho que el select (ambos son w-full
                 de esta misma columna). Cuadrada y recortada un poco hacia
                 arriba, como las miniaturas de la biblioteca: los thangkas
                 son verticales y la cara está en el tercio superior.
                 El tope en vh la achica en pantallas bajas y en apaisado: al
                 recortarse queda una franja centrada en la cara, no una
                 imagen aplastada, y el contador no se va de la pantalla. -->
            <img
                v-if="mantra?.image_url"
                :src="mantra.image_url"
                alt=""
                class="max-h-[30vh] w-full shrink-0 rounded-xl object-cover object-[50%_20%]"
                style="aspect-ratio: 1 / 1"
            />

            <!-- Mantra + contador -->
            <div
                v-if="mantra"
                class="flex flex-1 flex-col justify-center gap-5"
            >
                <div>
                    <p
                        class="text-xs tracking-widest text-muted-foreground uppercase"
                    >
                        {{ mantra.name }}
                    </p>
                    <p
                        class="mt-2 font-light whitespace-pre-line"
                        :class="mantraTextClass"
                    >
                        {{ mantra.text }}
                    </p>
                    <p
                        v-if="mantra.transliteration"
                        class="mt-1 text-xs text-muted-foreground italic"
                    >
                        {{ mantra.transliteration }}
                    </p>
                </div>

                <div>
                    <p
                        class="font-mono text-5xl font-extralight tabular-nums sm:text-6xl"
                        aria-live="polite"
                    >
                        {{ mala.snapshot.value.count }}
                    </p>
                    <p
                        v-if="followGoal"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ dailyProgressToday }} / {{ dailyGoalValue }}
                        {{ t('del objetivo de hoy') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <template v-if="mala.snapshot.value.round > 0">
                            {{ mala.snapshot.value.round }}
                            {{
                                mala.snapshot.value.round === 1
                                    ? t('mala completo')
                                    : t('malas completos')
                            }}
                            ·
                        </template>
                        {{
                            t(':count en esta sesión', {
                                count: String(mala.snapshot.value.totalCount),
                            })
                        }}
                    </p>
                </div>
            </div>

            <p
                v-else-if="mantras.length > 0"
                class="flex flex-1 items-center text-sm text-muted-foreground"
            >
                {{ t('Elegí un mantra para comenzar.') }}
            </p>
            <p
                v-else
                class="flex flex-1 items-center text-sm text-muted-foreground"
            >
                {{
                    t(
                        'Todavía no hay mantras en la memoria local. Conectate una vez para descargar tu biblioteca.',
                    )
                }}
            </p>
        </div>

        <!-- Recuperación de sesión interrumpida -->
        <div
            v-if="resumeCandidate"
            class="absolute inset-0 z-20 flex items-center justify-center bg-background/85 p-6 backdrop-blur-sm"
        >
            <div class="w-full max-w-sm space-y-4 text-center">
                <p class="text-sm text-muted-foreground">
                    {{ t('Tenés una práctica sin terminar con este mantra:') }}
                    <span class="font-medium text-foreground">
                        {{
                            t(':count recitaciones', {
                                count: String(resumeCandidate.totalCount),
                            })
                        }}
                    </span>
                </p>
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-md border bg-foreground px-4 py-2.5 text-sm font-medium text-background"
                        @click="resolveResume('continue')"
                    >
                        {{ t('Continuar donde quedé') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2.5 text-sm"
                        @click="resolveResume('finish-and-restart')"
                    >
                        {{ t('Guardarla y empezar de nuevo') }}
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
                class="absolute inset-0 z-20 flex cursor-default flex-col items-center justify-center gap-2 bg-background/70 backdrop-blur-[2px]"
                @click="celebration = null"
            >
                <span class="h-px w-10 bg-foreground/30" />
                <p class="px-6 text-center text-lg font-light">
                    {{ celebrationText }}
                </p>
                <span class="h-px w-10 bg-foreground/30" />
            </button>
        </Transition>
    </div>
</template>
