<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, Pencil, Settings2 } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Abacus from '@/components/retreats/Abacus.vue';
import ResetRetreatDialog from '@/components/retreats/ResetRetreatDialog.vue';
import { Button } from '@/components/ui/button';
import { getLocalDate } from '@/lib/practice/localDate';

interface DeityOption {
    id: number;
    name: string;
    image_url: string | null;
    stages: number;
}

interface Stage {
    id: number;
    name: string;
    text: string;
    goal: number;
    count: number;
    completed_on: string | null;
}

interface ActiveRetreat {
    id: number;
    deity: {
        id: number;
        name: string;
        image_url: string | null;
        syllable_image_url: string | null;
        color: string | null;
    };
    started_on: string;
    completed_on: string | null;
    notes: string | null;
    dedications: string | null;
    stages: Stage[];
    current_stage_id: number | null;
}

const props = defineProps<{
    deities: DeityOption[];
    retreat: ActiveRetreat | null;
    /** Solo los administradores cargan deidades y sus mantras. */
    canManageDeities: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Retiro de aproximación', href: '/retreats' }],
    },
});

const stage = computed<Stage | null>(
    () =>
        props.retreat?.stages.find(
            (s) => s.id === props.retreat?.current_stage_id,
        ) ?? null,
);

// El conteo se lleva acá y se sincroniza cada tanto: una recitación no puede
// costar un viaje al servidor.
const count = ref(stage.value?.count ?? 0);

// Al cambiar de etapa (o de deidad) el ábaco arranca donde quedó esa etapa.
watch(
    () => stage.value?.id,
    () => {
        flush();
        count.value = stage.value?.count ?? 0;
    },
);

const remaining = computed(() =>
    stage.value === null ? 0 : Math.max(0, stage.value.goal - count.value),
);

const reached = computed(
    () => stage.value !== null && count.value >= stage.value.goal,
);

const progressPercent = computed(() => {
    if (stage.value === null || stage.value.goal === 0) {
        return 0;
    }

    return Math.min(100, (count.value / stage.value.goal) * 100);
});

function formatNumber(value: number): string {
    return value.toLocaleString('es');
}

// ── Persistencia ────────────────────────────────────────────────────────────
// El valor viaja ABSOLUTO, así que un reintento nunca duplica. Se manda con
// retardo para no pegarle al servidor una vez por cuenta.
const SYNC_DELAY = 800;

let timer: ReturnType<typeof setTimeout> | null = null;
let pending = false;

/** Copia local por si el envío no llega: un retiro no puede perder cuentas. */
function storageKey(): string | null {
    if (props.retreat === null || stage.value === null) {
        return null;
    }

    return `retreat:${props.retreat.id}:${stage.value.id}`;
}

function remember(value: number): void {
    const key = storageKey();

    if (key === null) {
        return;
    }

    try {
        localStorage.setItem(key, String(value));
    } catch {
        // Modo privado o almacenamiento lleno: se sigue igual, sin red de
        // seguridad pero sin romper el conteo.
    }
}

function forget(): void {
    const key = storageKey();

    if (key === null) {
        return;
    }

    try {
        localStorage.removeItem(key);
    } catch {
        // Ídem.
    }
}

function onCount(value: number): void {
    count.value = value;
    remember(value);
    pending = true;

    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout(flush, SYNC_DELAY);
}

function flush(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    if (!pending || props.retreat === null || stage.value === null) {
        return;
    }

    const sent = count.value;
    pending = false;

    router.patch(
        `/retreats/${props.retreat.id}/count`,
        { retreat_mantra_id: stage.value.id, count: sent },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => forget(),
            // Si falló, queda pendiente: el próximo toque lo reintenta y el
            // valor guardado permite recuperarlo al volver a entrar.
            onError: () => {
                pending = true;
            },
        },
    );
}

function onHidden(): void {
    if (document.visibilityState === 'hidden') {
        flush();
        flushNotes();
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', onHidden);

    // Si quedó un conteo sin sincronizar de una visita anterior y es mayor
    // que el del servidor, se recupera.
    const key = storageKey();

    if (key === null) {
        return;
    }

    try {
        const saved = Number(localStorage.getItem(key));

        if (Number.isFinite(saved) && saved > count.value) {
            count.value = saved;
            pending = true;
            flush();
        }
    } catch {
        // Sin almacenamiento no hay nada que recuperar.
    }
});

// ── Notas ───────────────────────────────────────────────────────────────────
// Apuntes libres durante el retiro: mismo patrón que el conteo (debounced,
// con flush en los mismos puntos), pero por retiro y no por etapa.
const NOTES_SYNC_DELAY = 1200;
const notes = ref(props.retreat?.notes ?? '');
let notesTimer: ReturnType<typeof setTimeout> | null = null;
let notesPending = false;

function onNotesInput(): void {
    notesPending = true;

    if (notesTimer !== null) {
        clearTimeout(notesTimer);
    }

    notesTimer = setTimeout(flushNotes, NOTES_SYNC_DELAY);
}

function flushNotes(): void {
    if (notesTimer !== null) {
        clearTimeout(notesTimer);
        notesTimer = null;
    }

    if (!notesPending || props.retreat === null) {
        return;
    }

    const sent = notes.value;
    notesPending = false;

    router.patch(
        `/retreats/${props.retreat.id}`,
        { notes: sent },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                notesPending = true;
            },
        },
    );
}

// ── Dedicaciones ────────────────────────────────────────────────────────────
// A diferencia de las notas, no se autoguardan: se editan a mano con el
// lápiz y se confirman con Guardar, como pidió el usuario.
const DEDICATIONS_PREVIEW_CHARS = 220;
const dedications = ref(props.retreat?.dedications ?? '');
const editingDedications = ref(false);
const dedicationsDraft = ref('');
const dedicationsExpanded = ref(false);
const savingDedications = ref(false);

const dedicationsIsLong = computed(
    () => dedications.value.trim().length > DEDICATIONS_PREVIEW_CHARS,
);

function startEditingDedications(): void {
    dedicationsDraft.value = dedications.value;
    editingDedications.value = true;
}

function cancelEditingDedications(): void {
    editingDedications.value = false;
}

function saveDedications(): void {
    if (props.retreat === null) {
        return;
    }

    savingDedications.value = true;

    router.patch(
        `/retreats/${props.retreat.id}`,
        { dedications: dedicationsDraft.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                dedications.value = dedicationsDraft.value;
                editingDedications.value = false;
            },
            onFinish: () => {
                savingDedications.value = false;
            },
        },
    );
}

// Cambiar de deidad trae otro retiro: notas y dedicación arrancan de nuevo
// desde lo que traiga esa fila, no desde lo que quedó tipeado.
watch(
    () => props.retreat?.id,
    () => {
        notes.value = props.retreat?.notes ?? '';
        dedications.value = props.retreat?.dedications ?? '';
        editingDedications.value = false;
        dedicationsExpanded.value = false;
    },
);

const textareaClass =
    'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onHidden);
    flush();
    flushNotes();
});

// ── Acciones ────────────────────────────────────────────────────────────────
const switching = ref(false);

function activate(deityId: number): void {
    flush();
    flushNotes();
    switching.value = true;
    router.post(
        '/retreats/activate',
        { retreat_deity_id: deityId, local_date: getLocalDate() },
        { preserveScroll: true, onFinish: () => (switching.value = false) },
    );
}

function onDeityChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (value) {
        activate(Number(value));
    }
}

/** Cierra la etapa en curso. La cierra el usuario, no la cifra. */
function completeStage(): void {
    if (props.retreat === null || stage.value === null) {
        return;
    }

    flush();
    router.patch(
        `/retreats/${props.retreat.id}/stage`,
        {
            retreat_mantra_id: stage.value.id,
            completed: true,
            local_date: getLocalDate(),
        },
        { preserveScroll: true },
    );
}

/**
 * El diálogo ya confirmó el reinicio contra el servidor sin preserveState,
 * así que las props (stages, current_stage_id) llegan frescas en 0. Lo que
 * hay que arreglar a mano es lo que vive fuera de las props: el ref local
 * del conteo en curso (si no, sigue mostrando el número viejo hasta la
 * próxima recarga), un debounce pendiente de ANTES del reinicio (que si
 * llegara tarde pisaría el 0 con el número viejo) y la copia de seguridad
 * en localStorage de cada etapa (si quedara un número viejo ahí, la
 * recuperación al montar la traería de vuelta).
 */
function onReset(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    pending = false;
    dismissed.value = false;
    count.value = stage.value?.count ?? 0;

    if (props.retreat !== null) {
        for (const item of props.retreat.stages) {
            try {
                localStorage.removeItem(
                    `retreat:${props.retreat.id}:${item.id}`,
                );
            } catch {
                // Sin almacenamiento no hay nada que limpiar.
            }
        }
    }
}

const dismissed = ref(false);

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="t('Retiro de aproximación')" />

    <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ t('Retiro de aproximación') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('Llevá la cuenta con el contador de tres líneas.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <ResetRetreatDialog
                    v-if="retreat !== null"
                    :retreat-id="retreat.id"
                    :deity-name="retreat.deity.name"
                    @reset="onReset"
                />
                <Button
                    v-if="canManageDeities"
                    as-child
                    size="sm"
                    variant="outline"
                >
                    <Link href="/retreats/deities">
                        <Settings2 class="size-4" />
                        {{ t('Deidades') }}
                    </Link>
                </Button>
            </div>
        </header>

        <!-- Sin retiro: elegir la deidad -->
        <div v-if="retreat === null" class="space-y-3">
            <p
                v-if="deities.length === 0"
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('Todavía no hay deidades cargadas.') }}
            </p>

            <template v-else>
                <p class="text-sm text-muted-foreground">
                    {{ t('Elegí la deidad del retiro.') }}
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        v-for="deity in deities"
                        :key="deity.id"
                        type="button"
                        :disabled="switching"
                        class="flex flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:bg-accent disabled:opacity-50"
                        @click="activate(deity.id)"
                    >
                        <img
                            v-if="deity.image_url"
                            :src="deity.image_url"
                            :alt="deity.name"
                            class="size-20 rounded-lg object-cover"
                        />
                        <span
                            v-else
                            class="flex size-20 items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground"
                        >
                            {{ t('Sin imagen') }}
                        </span>
                        <span class="text-sm font-medium">{{
                            deity.name
                        }}</span>
                    </button>
                </div>
            </template>
        </div>

        <!-- Retiro en curso -->
        <template v-else>
            <div class="grid gap-2">
                <label for="deity" class="text-sm font-medium">
                    {{ t('Deidad') }}
                </label>
                <select
                    id="deity"
                    :class="selectClass"
                    :disabled="switching"
                    @change="onDeityChange"
                >
                    <option
                        v-for="deity in deities"
                        :key="deity.id"
                        :value="deity.id"
                        :selected="deity.id === retreat.deity.id"
                    >
                        {{ deity.name }}
                    </option>
                </select>
            </div>

            <div class="flex items-start gap-4">
                <img
                    v-if="retreat.deity.image_url"
                    :src="retreat.deity.image_url"
                    :alt="retreat.deity.name"
                    class="size-24 shrink-0 rounded-xl object-cover"
                />
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold">
                        {{ retreat.deity.name }}
                    </h2>
                    <p v-if="stage" class="text-sm text-muted-foreground">
                        {{ stage.name }}
                    </p>
                </div>
                <img
                    v-if="retreat.deity.syllable_image_url"
                    :src="retreat.deity.syllable_image_url"
                    :alt="t('Sílaba de :name', { name: retreat.deity.name })"
                    class="size-16 shrink-0 rounded-xl object-contain"
                />
            </div>

            <p
                v-if="stage === null"
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{
                    retreat.stages.length === 0
                        ? t('Esta deidad todavía no tiene mantras cargados.')
                        : t('Completaste todas las etapas de este retiro.')
                }}
            </p>

            <template v-else>
                <p
                    class="rounded-xl border bg-card/50 p-4 leading-relaxed font-light whitespace-pre-line"
                >
                    {{ stage.text }}
                </p>

                <div class="space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-3xl font-semibold tabular-nums">
                            {{ formatNumber(count) }}
                        </span>
                        <span
                            class="text-sm text-muted-foreground tabular-nums"
                        >
                            {{
                                t('de :goal', {
                                    goal: formatNumber(stage.goal),
                                })
                            }}
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-foreground transition-[width] duration-300"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{
                            reached
                                ? t('Cifra cumplida')
                                : t('Faltan :count', {
                                      count: formatNumber(remaining),
                                  })
                        }}
                    </p>
                </div>

                <div
                    v-if="reached && !dismissed"
                    class="space-y-3 rounded-xl border border-foreground/20 bg-accent/60 p-4"
                >
                    <p class="text-sm font-medium">
                        {{
                            t('Completaste las :goal de :name.', {
                                goal: formatNumber(stage.goal),
                                name: stage.name,
                            })
                        }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" @click="completeStage">
                            <Check class="size-4" />
                            {{ t('Pasar a la siguiente') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="dismissed = true"
                        >
                            {{ t('Seguir contando') }}
                        </Button>
                    </div>
                </div>

                <Abacus
                    :count="count"
                    :data-color="retreat.deity.color ?? undefined"
                    @update:count="onCount"
                />

                <p class="text-center text-xs text-muted-foreground">
                    {{ t('Empujá una cuenta hacia la derecha para contar.') }}
                </p>
            </template>

            <div v-if="retreat.stages.length > 1" class="space-y-2">
                <h3 class="text-sm font-medium">{{ t('Etapas') }}</h3>
                <ol class="space-y-1">
                    <li
                        v-for="(item, index) in retreat.stages"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                        :class="{
                            'border-foreground/30': item.id === stage?.id,
                            'text-muted-foreground': item.completed_on !== null,
                        }"
                    >
                        <span class="min-w-0 truncate">
                            {{ index + 1 }}. {{ item.name }}
                        </span>
                        <span class="shrink-0 tabular-nums">
                            <Check
                                v-if="item.completed_on"
                                class="inline size-4"
                            />
                            {{
                                formatNumber(
                                    item.id === stage?.id ? count : item.count,
                                )
                            }}
                            /
                            {{ formatNumber(item.goal) }}
                        </span>
                    </li>
                </ol>
            </div>

            <!-- Apuntes libres de esta sesión, privados y autoguardados. -->
            <div class="space-y-2">
                <label for="retreat-notes" class="text-sm font-medium">
                    {{ t('Notas') }}
                </label>
                <textarea
                    id="retreat-notes"
                    v-model="notes"
                    :class="textareaClass"
                    rows="4"
                    :placeholder="
                        t(
                            'Apuntes de esta sesión, instrucciones del lama, lo que quieras recordar…',
                        )
                    "
                    @input="onNotesInput"
                    @blur="flushNotes"
                />
            </div>

            <!-- Dedicaciones: plegadas a las primeras líneas, con el lápiz
                 para agregar o borrar texto. Va al pie de todo. -->
            <div class="space-y-2 border-t pt-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-medium">
                        {{ t('Dedicaciones del retiro') }}
                    </h3>
                    <button
                        v-if="!editingDedications"
                        type="button"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="t('Editar dedicaciones')"
                        :title="t('Editar dedicaciones')"
                        @click="startEditingDedications"
                    >
                        <Pencil class="size-4" />
                    </button>
                </div>

                <template v-if="editingDedications">
                    <textarea
                        v-model="dedicationsDraft"
                        :class="textareaClass"
                        rows="6"
                        :placeholder="
                            t('El texto de dedicación de este retiro…')
                        "
                    />
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            :disabled="savingDedications"
                            @click="saveDedications"
                        >
                            {{ t('Guardar') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="savingDedications"
                            @click="cancelEditingDedications"
                        >
                            {{ t('Cancelar') }}
                        </Button>
                    </div>
                </template>

                <template v-else>
                    <p
                        v-if="dedications.trim() === ''"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('Todavía no hay dedicaciones cargadas.') }}
                    </p>
                    <template v-else>
                        <p
                            class="text-sm leading-relaxed whitespace-pre-line"
                            :class="{ 'line-clamp-3': !dedicationsExpanded }"
                        >
                            {{ dedications }}
                        </p>
                        <button
                            v-if="dedicationsIsLong"
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                            @click="dedicationsExpanded = !dedicationsExpanded"
                        >
                            {{
                                dedicationsExpanded
                                    ? t('Mostrar menos')
                                    : t('Mostrar más')
                            }}
                            <ChevronDown
                                class="size-3.5 transition-transform"
                                :class="{ 'rotate-180': dedicationsExpanded }"
                            />
                        </button>
                    </template>
                </template>
            </div>
        </template>
    </div>
</template>
