<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Pencil, Plus } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { getLocalDate } from '@/lib/practice/localDate';

interface Recitation {
    id: number;
    title: string;
    text: string;
    color: string | null;
    daily_commitment: number | null;
    today_count: number;
}

const props = defineProps<{
    recitations: Recitation[];
    localDate: string;
    /** Solo los administradores: los textos son los mismos para todas las cuentas. */
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Otras recitaciones', href: '/recitations' }],
    },
});

// Varias abiertas a la vez: en una sesión se encadenan (los cuatro
// inconmensurables, después la promesa, después los votos).
const open = ref<Set<number>>(new Set());

function toggle(id: number): void {
    const next = new Set(open.value);

    if (!next.delete(id)) {
        next.add(id);
    }

    open.value = next;
}

/**
 * El día lo pone el dispositivo (§7). Si el servidor renderizó con otra
 * fecha (la pestaña quedó abierta cruzando la medianoche), se recarga para
 * no registrar contra el día equivocado.
 */
function deviceDate(): string {
    return getLocalDate();
}

// ── Registrar recitaciones ──────────────────────────────────────────────────
const registerFor = ref<Recitation | null>(null);
const registerAmount = ref<number>(1);
const registerInput = ref<HTMLInputElement | null>(null);
const saving = ref(false);

async function openRegister(recitation: Recitation): Promise<void> {
    registerFor.value = recitation;
    registerAmount.value = 1;
    await nextTick();
    registerInput.value?.select();
}

function saveRegister(): void {
    const recitation = registerFor.value;
    const count = registerAmount.value;

    if (!recitation || !Number.isInteger(count) || count < 1) {
        return;
    }

    saving.value = true;
    router.post(
        `/recitations/${recitation.id}/log`,
        { count, local_date: deviceDate() },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                registerFor.value = null;
            },
        },
    );
}

// ── Compromiso diario ───────────────────────────────────────────────────────
const commitmentFor = ref<Recitation | null>(null);
const commitmentValue = ref<number | null>(null);

function openCommitment(recitation: Recitation): void {
    commitmentFor.value = recitation;
    commitmentValue.value = recitation.daily_commitment;
}

function saveCommitment(): void {
    const recitation = commitmentFor.value;

    if (!recitation) {
        return;
    }

    saving.value = true;
    router.patch(
        `/recitations/${recitation.id}/commitment`,
        { daily_commitment: commitmentValue.value },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                commitmentFor.value = null;
            },
        },
    );
}

function progressLabel(recitation: Recitation): string {
    if (recitation.daily_commitment !== null) {
        return `${recitation.today_count} / ${recitation.daily_commitment} ${t('de hoy')}`;
    }

    return t(':count hoy', { count: String(recitation.today_count) });
}
</script>

<template>
    <Head :title="t('Otras recitaciones')" />

    <div class="space-y-6 px-4 py-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ t('Otras recitaciones') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Oraciones y yogas para leer. Su compromiso es propio: no comparte cuenta con los mantras.',
                        )
                    }}
                </p>
            </div>

            <Button v-if="canManage" as-child size="sm">
                <Link href="/recitations/create">
                    <Plus class="size-4" />
                    {{ t('Nueva recitación') }}
                </Link>
            </Button>
        </header>

        <div class="flex flex-col gap-3">
            <div
                v-for="recitation in props.recitations"
                :key="recitation.id"
                :data-color="recitation.color ?? undefined"
                class="recitation-card rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="flex items-center">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-between gap-3 rounded-xl p-4 text-left transition-colors hover:bg-accent/50"
                        :aria-expanded="open.has(recitation.id)"
                        :aria-controls="`recitation-${recitation.id}`"
                        @click="toggle(recitation.id)"
                    >
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ recitation.title }}
                            </span>
                            <span
                                class="mt-0.5 block text-xs text-muted-foreground tabular-nums"
                                :class="{
                                    'text-foreground':
                                        recitation.daily_commitment !== null &&
                                        recitation.today_count >=
                                            recitation.daily_commitment,
                                }"
                            >
                                {{ progressLabel(recitation) }}
                            </span>
                        </span>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': open.has(recitation.id) }"
                        />
                    </button>

                    <!-- Fuera del botón que despliega: un enlace anidado en un
                         button no es HTML válido y el click se pelearía con el
                         toggle. -->
                    <Link
                        v-if="canManage"
                        :href="`/recitations/${recitation.id}/edit`"
                        class="mr-2 inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="t('Editar')"
                        :title="t('Editar')"
                    >
                        <Pencil class="size-4" />
                    </Link>
                </div>

                <div v-show="open.has(recitation.id)" class="px-4 pb-4">
                    <!-- whitespace-pre-line: el texto trae su propia estructura
                         de versos y párrafos desde el seeder. -->
                    <p
                        :id="`recitation-${recitation.id}`"
                        class="leading-relaxed font-light whitespace-pre-line"
                    >
                        {{ recitation.text }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            class="rounded-md border border-input px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            @click="openRegister(recitation)"
                        >
                            {{ t('Registrar') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-input px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            @click="openCommitment(recitation)"
                        >
                            {{
                                recitation.daily_commitment === null
                                    ? t('Fijar compromiso')
                                    : t('Cambiar compromiso')
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registrar -->
        <div
            v-if="registerFor"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/85 p-6 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="register-recitation-title"
            @keydown.esc="registerFor = null"
        >
            <form
                class="w-full max-w-sm space-y-4"
                @submit.prevent="saveRegister"
            >
                <div>
                    <h2 id="register-recitation-title" class="font-medium">
                        {{ t('Registrar recitaciones') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ registerFor.title }}
                    </p>
                </div>
                <input
                    ref="registerInput"
                    v-model.number="registerAmount"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="10000"
                    required
                    :aria-label="t('Cantidad de recitaciones')"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-center font-mono text-2xl text-foreground tabular-nums focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                />
                <div class="flex flex-col gap-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-md border bg-foreground px-4 py-2.5 text-sm font-medium text-background disabled:opacity-50"
                    >
                        {{ saving ? t('Guardando…') : t('Registrar') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2.5 text-sm"
                        @click="registerFor = null"
                    >
                        {{ t('Cancelar') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Compromiso diario -->
        <div
            v-if="commitmentFor"
            class="fixed inset-0 z-50 flex items-center justify-center bg-background/85 p-6 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="commitment-title"
            @keydown.esc="commitmentFor = null"
        >
            <form
                class="w-full max-w-sm space-y-4"
                @submit.prevent="saveCommitment"
            >
                <div>
                    <h2 id="commitment-title" class="font-medium">
                        {{ t('Compromiso diario') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ commitmentFor.title }}
                    </p>
                </div>
                <input
                    v-model.number="commitmentValue"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="10000"
                    :aria-label="t('Veces por día')"
                    :placeholder="t('Veces por día')"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-center font-mono text-2xl text-foreground tabular-nums focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                />
                <div class="flex flex-col gap-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-md border bg-foreground px-4 py-2.5 text-sm font-medium text-background disabled:opacity-50"
                    >
                        {{ saving ? t('Guardando…') : t('Guardar') }}
                    </button>
                    <button
                        v-if="commitmentFor.daily_commitment !== null"
                        type="button"
                        class="rounded-md border px-4 py-2.5 text-sm text-muted-foreground"
                        @click="
                            commitmentValue = null;
                            saveCommitment();
                        "
                    >
                        {{ t('Quitar compromiso') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2.5 text-sm"
                        @click="commitmentFor = null"
                    >
                        {{ t('Cancelar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
