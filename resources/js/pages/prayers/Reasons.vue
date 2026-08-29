<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, X } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface Reason {
    id: number;
    name: string;
    color: string | null;
    position: number;
    /** Está en la lista de alguien, así que no se puede borrar. */
    in_use: boolean;
}

const props = defineProps<{
    reasons: Reason[];
    colors: { value: string; label: string }[];
    nextPosition: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lista de oración', href: '/prayers' },
            { title: 'Motivos', href: '/prayers/reasons' },
        ],
    },
});

// El intento de borrar un motivo en uso vuelve por withErrors, no por el
// formulario: se muestra arriba de todo.
const page = usePage();
const globalErrors = computed<string[]>(() => {
    const message = (page.props.errors as Record<string, string>)?.reason;

    return message ? [message] : [];
});

const createForm = useForm({
    name: '',
    color: null as string | null,
    position: props.nextPosition,
});

function create(): void {
    createForm.post('/prayers/reasons', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            // reset() vuelve al default que se capturó al montar; el orden
            // sugerido ya avanzó con el motivo recién creado.
            createForm.position = props.nextPosition;
        },
    });
}

// ── Edición en línea ────────────────────────────────────────────────────────
const editing = ref<number | null>(null);
const editForm = useForm({
    name: '',
    color: null as string | null,
    position: 0,
});

function startEditing(reason: Reason): void {
    editing.value = reason.id;
    editForm.defaults({
        name: reason.name,
        color: reason.color,
        position: reason.position,
    });
    editForm.reset();
    editForm.clearErrors();
}

function saveEditing(): void {
    if (editing.value === null) {
        return;
    }

    editForm.patch(`/prayers/reasons/${editing.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function destroy(reason: Reason): void {
    if (
        confirm(
            t(
                '¿Eliminar este motivo? Deja de estar disponible para todas las cuentas.',
            ),
        )
    ) {
        router.delete(`/prayers/reasons/${reason.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="t('Motivos de oración')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <Link
                href="/prayers"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ t('Lista de oración') }}
            </Link>
            <h1 class="text-xl font-semibold">{{ t('Motivos de oración') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Quedan disponibles para todas las cuentas.') }}
            </p>
        </div>

        <AlertError v-if="globalErrors.length" :errors="globalErrors" />

        <form class="grid gap-4 rounded-xl border p-4" @submit.prevent="create">
            <div class="grid gap-2">
                <Label for="new-reason">{{ t('Motivo nuevo') }} *</Label>
                <Input
                    id="new-reason"
                    v-model="createForm.name"
                    required
                    maxlength="80"
                    autocomplete="off"
                    :placeholder="t('Por ejemplo: Larga vida del maestro')"
                />
                <InputError :message="createForm.errors.name" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ t('Color del chip') }}
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="size-9 rounded-full border-2 border-dashed border-input transition-transform hover:scale-105"
                        :class="{
                            'ring-2 ring-ring ring-offset-2': !createForm.color,
                        }"
                        :aria-label="t('Sin color')"
                        :aria-pressed="!createForm.color"
                        @click="createForm.color = null"
                    />
                    <button
                        v-for="color in colors"
                        :key="color.value"
                        type="button"
                        class="mantra-swatch size-9 rounded-full border transition-transform hover:scale-105"
                        :data-color="color.value"
                        :class="{
                            'ring-2 ring-ring ring-offset-2':
                                createForm.color === color.value,
                        }"
                        :aria-label="color.label"
                        :aria-pressed="createForm.color === color.value"
                        @click="createForm.color = color.value"
                    />
                </div>
                <InputError :message="createForm.errors.color" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="new-position">{{ t('Orden') }}</Label>
                <Input
                    id="new-position"
                    v-model="createForm.position"
                    type="number"
                    min="0"
                    class="max-w-24"
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        t(
                            'Menor primero. Con el mismo número, ordena por nombre.',
                        )
                    }}
                </p>
                <InputError :message="createForm.errors.position" />
            </div>

            <div>
                <Button :disabled="createForm.processing">
                    <Spinner v-if="createForm.processing" />
                    <Plus v-else class="size-4" />
                    {{ t('Crear motivo') }}
                </Button>
            </div>
        </form>

        <div class="flex flex-col gap-2">
            <div
                v-for="reason in reasons"
                :key="reason.id"
                class="rounded-xl border p-3"
            >
                <div
                    v-if="editing !== reason.id"
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <span
                            class="prayer-chip rounded-full border px-2.5 py-0.5 text-sm"
                            :data-color="reason.color ?? undefined"
                        >
                            {{ reason.name }}
                        </span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            #{{ reason.position }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            :aria-label="t('Editar')"
                            :title="t('Editar')"
                            @click="startEditing(reason)"
                        >
                            <Pencil class="size-4" />
                        </button>
                        <button
                            type="button"
                            :disabled="reason.in_use"
                            class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40"
                            :aria-label="t('Eliminar')"
                            :title="
                                reason.in_use
                                    ? t('Está en uso en alguna lista.')
                                    : t('Eliminar')
                            "
                            @click="destroy(reason)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <form v-else class="grid gap-3" @submit.prevent="saveEditing">
                    <div class="grid gap-2">
                        <Label :for="`reason-${reason.id}`">
                            {{ t('Nombre') }}
                        </Label>
                        <Input
                            :id="`reason-${reason.id}`"
                            v-model="editForm.name"
                            required
                            maxlength="80"
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="size-8 rounded-full border-2 border-dashed border-input transition-transform hover:scale-105"
                            :class="{
                                'ring-2 ring-ring ring-offset-2':
                                    !editForm.color,
                            }"
                            :aria-label="t('Sin color')"
                            :aria-pressed="!editForm.color"
                            @click="editForm.color = null"
                        />
                        <button
                            v-for="color in colors"
                            :key="color.value"
                            type="button"
                            class="mantra-swatch size-8 rounded-full border transition-transform hover:scale-105"
                            :data-color="color.value"
                            :class="{
                                'ring-2 ring-ring ring-offset-2':
                                    editForm.color === color.value,
                            }"
                            :aria-label="color.label"
                            :aria-pressed="editForm.color === color.value"
                            @click="editForm.color = color.value"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`position-${reason.id}`">
                            {{ t('Orden') }}
                        </Label>
                        <Input
                            :id="`position-${reason.id}`"
                            v-model="editForm.position"
                            type="number"
                            min="0"
                            class="max-w-24"
                        />
                        <InputError :message="editForm.errors.position" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button size="sm" :disabled="editForm.processing">
                            <Spinner v-if="editForm.processing" />
                            {{ t('Guardar') }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="editing = null"
                        >
                            {{ t('Cancelar') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
