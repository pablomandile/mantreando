<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Pencil, Plus, Tags } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

interface Reason {
    id: number;
    name: string;
    color: string | null;
}

interface Intention {
    id: number;
    name: string;
    custom_reason: string | null;
    archived_at: string | null;
    reason_ids: number[];
    reasons: Reason[];
}

const props = defineProps<{
    intentions: Intention[];
    /** El catálogo completo, para filtrar. */
    reasons: Reason[];
    showingArchived: boolean;
    activeCount: number;
    archivedCount: number;
    /** Solo los administradores mantienen el catálogo de motivos. */
    canManageReasons: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lista de oración', href: '/prayers' }],
    },
});

// Filtro por motivo, del lado del cliente: la lista es corta y así no se
// pierde el lugar ni hace falta ir al servidor.
const filter = ref<number | null>(null);

const visible = computed<Intention[]>(() => {
    const id = filter.value;

    return id === null
        ? props.intentions
        : props.intentions.filter((intention) =>
              intention.reason_ids.includes(id),
          );
});

/** Solo los motivos que alguien está usando: filtrar por uno vacío no sirve. */
const usableFilters = computed<Reason[]>(() =>
    props.reasons.filter((reason) =>
        props.intentions.some((intention) =>
            intention.reason_ids.includes(reason.id),
        ),
    ),
);

// La tarjeta toma el color del primer motivo, para que la lista se lea por
// color de un vistazo, como la biblioteca de mantras.
function cardColor(intention: Intention): string | undefined {
    return intention.reasons[0]?.color ?? undefined;
}

const saving = ref<number | null>(null);

function setArchived(intention: Intention, archived: boolean): void {
    saving.value = intention.id;
    router.patch(
        `/prayers/${intention.id}/archive`,
        { archived },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = null;
            },
        },
    );
}

/** Mediodía, no medianoche: evita que la fecha se corra de día por la zona. */
function formatDate(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString('es', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

const tabClass =
    'rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent hover:text-foreground';
</script>

<template>
    <Head :title="t('Lista de oración')" />

    <div class="space-y-6 px-4 py-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ t('Lista de oración') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Por quién orás y por qué, para tenerlos presentes todos los días.',
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canManageReasons"
                    as-child
                    size="sm"
                    variant="outline"
                >
                    <Link href="/prayers/reasons">
                        <Tags class="size-4" />
                        {{ t('Motivos') }}
                    </Link>
                </Button>
                <Button as-child size="sm">
                    <Link href="/prayers/create">
                        <Plus class="size-4" />
                        {{ t('Agregar') }}
                    </Link>
                </Button>
            </div>
        </header>

        <div class="flex flex-wrap items-center gap-1 border-b pb-2">
            <Link
                href="/prayers"
                :class="[
                    tabClass,
                    showingArchived
                        ? 'text-muted-foreground'
                        : 'bg-accent font-medium text-foreground',
                ]"
            >
                {{ t('En la lista') }}
                <span class="tabular-nums">({{ activeCount }})</span>
            </Link>
            <Link
                href="/prayers?archived=1"
                :class="[
                    tabClass,
                    showingArchived
                        ? 'bg-accent font-medium text-foreground'
                        : 'text-muted-foreground',
                ]"
            >
                {{ t('Archivados') }}
                <span class="tabular-nums">({{ archivedCount }})</span>
            </Link>
        </div>

        <div v-if="usableFilters.length > 1" class="flex flex-wrap gap-1.5">
            <button
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                :class="
                    filter === null
                        ? 'border-foreground text-foreground'
                        : 'border-input text-muted-foreground hover:bg-accent'
                "
                :aria-pressed="filter === null"
                @click="filter = null"
            >
                {{ t('Todos') }}
            </button>
            <button
                v-for="reason in usableFilters"
                :key="reason.id"
                type="button"
                class="prayer-chip rounded-full border px-2.5 py-1 text-xs transition-colors"
                :data-color="reason.color ?? undefined"
                :class="{
                    'ring-2 ring-ring ring-offset-1': filter === reason.id,
                }"
                :aria-pressed="filter === reason.id"
                @click="filter = filter === reason.id ? null : reason.id"
            >
                {{ reason.name }}
            </button>
        </div>

        <p
            v-if="intentions.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{
                showingArchived
                    ? t('Todavía no archivaste a nadie.')
                    : t(
                          'Todavía no hay nadie en tu lista. Agregá a la primera persona.',
                      )
            }}
        </p>

        <div v-else class="flex flex-col gap-3">
            <article
                v-for="intention in visible"
                :key="intention.id"
                :data-color="cardColor(intention)"
                class="prayer-card flex items-start justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="min-w-0">
                    <h2 class="font-medium break-words">
                        {{ intention.name }}
                    </h2>

                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <span
                            v-for="reason in intention.reasons"
                            :key="reason.id"
                            class="prayer-chip rounded-full border px-2.5 py-0.5 text-xs"
                            :data-color="reason.color ?? undefined"
                        >
                            {{ reason.name }}
                        </span>
                        <span
                            v-if="intention.custom_reason"
                            class="rounded-full border border-dashed px-2.5 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ intention.custom_reason }}
                        </span>
                    </div>

                    <p
                        v-if="intention.archived_at"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        {{
                            t('Archivado el :date', {
                                date: formatDate(intention.archived_at),
                            })
                        }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        :href="`/prayers/${intention.id}/edit`"
                        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="t('Editar')"
                        :title="t('Editar')"
                    >
                        <Pencil class="size-4" />
                    </Link>
                    <button
                        type="button"
                        :disabled="saving === intention.id"
                        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
                        :aria-label="
                            intention.archived_at
                                ? t('Devolver a la lista')
                                : t('Archivar')
                        "
                        :title="
                            intention.archived_at
                                ? t('Devolver a la lista')
                                : t('Archivar')
                        "
                        @click="setArchived(intention, !intention.archived_at)"
                    >
                        <ArchiveRestore
                            v-if="intention.archived_at"
                            class="size-4"
                        />
                        <Archive v-else class="size-4" />
                    </button>
                </div>
            </article>
        </div>
    </div>
</template>
