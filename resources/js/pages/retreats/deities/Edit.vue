<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, X } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import ImagePicker from '@/components/retreats/ImagePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface Stage {
    id: number;
    name: string;
    text: string;
    goal: number;
    position: number;
    /** Alguien ya contó con este mantra, así que no se borra. */
    in_use: boolean;
}

const props = defineProps<{
    deity: {
        id: number;
        name: string;
        color: string | null;
        position: number;
        image_path: string | null;
        image_url: string | null;
        syllable_image_path: string | null;
        syllable_image_url: string | null;
    };
    stages: Stage[];
    colors: { value: string; label: string }[];
    gallery: { path: string; url: string }[];
    nextStagePosition: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Retiro de aproximación', href: '/retreats' },
            { title: 'Deidades', href: '/retreats/deities' },
            { title: 'Editar', href: '' },
        ],
    },
});

const page = usePage();
const globalErrors = computed<string[]>(() => {
    const errors = page.props.errors as Record<string, string>;
    const messages = [errors?.deity, errors?.stage].filter(Boolean);

    return messages as string[];
});

// ── La deidad ───────────────────────────────────────────────────────────────
const form = useForm({
    name: props.deity.name,
    color: props.deity.color,
    position: props.deity.position,
    image_path: props.deity.image_path,
    syllable_image_path: props.deity.syllable_image_path,
    image: null as File | null,
    syllable_image: null as File | null,
});

function save(): void {
    // La ruta ya es POST porque el formulario lleva archivos: no hace falta
    // el _method spoofing.
    form.post(`/retreats/deities/${props.deity.id}`, { forceFormData: true });
}

// ── Las etapas ──────────────────────────────────────────────────────────────
const stageForm = useForm({
    name: '',
    text: '',
    goal: 100000,
    position: props.nextStagePosition,
});

function addStage(): void {
    stageForm.post(`/retreats/deities/${props.deity.id}/mantras`, {
        preserveScroll: true,
        onSuccess: () => {
            stageForm.reset();
            // reset() vuelve al default de cuando se montó; el orden sugerido
            // ya avanzó con el mantra recién creado.
            stageForm.position = props.nextStagePosition;
        },
    });
}

const editing = ref<number | null>(null);
const editForm = useForm({ name: '', text: '', goal: 0, position: 0 });

function startEditing(stage: Stage): void {
    editing.value = stage.id;
    editForm.defaults({
        name: stage.name,
        text: stage.text,
        goal: stage.goal,
        position: stage.position,
    });
    editForm.reset();
    editForm.clearErrors();
}

function saveEditing(): void {
    if (editing.value === null) {
        return;
    }

    editForm.patch(`/retreats/mantras/${editing.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function destroyStage(stage: Stage): void {
    if (confirm(t('¿Eliminar este mantra del retiro?'))) {
        router.delete(`/retreats/mantras/${stage.id}`, {
            preserveScroll: true,
        });
    }
}

const textareaClass =
    'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="`${t('Deidad del retiro')} — ${deity.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <div>
            <Link
                href="/retreats/deities"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ t('Deidades del retiro') }}
            </Link>
            <h1 class="text-xl font-semibold">{{ deity.name }}</h1>
        </div>

        <AlertError v-if="globalErrors.length" :errors="globalErrors" />

        <form class="grid gap-5 rounded-xl border p-4" @submit.prevent="save">
            <div class="grid gap-2">
                <Label for="name">{{ t('Nombre') }} *</Label>
                <Input id="name" v-model="form.name" required maxlength="120" />
                <InputError :message="form.errors.name" />
            </div>

            <ImagePicker
                :label="t('Imagen de la deidad')"
                :current-url="deity.image_url"
                :path="form.image_path"
                :gallery="gallery"
                :error="form.errors.image ?? form.errors.image_path"
                @update:path="form.image_path = $event"
                @update:file="form.image = $event"
            />

            <ImagePicker
                :label="t('Imagen de la sílaba')"
                :current-url="deity.syllable_image_url"
                :path="form.syllable_image_path"
                :gallery="gallery"
                :error="
                    form.errors.syllable_image ??
                    form.errors.syllable_image_path
                "
                @update:path="form.syllable_image_path = $event"
                @update:file="form.syllable_image = $event"
            />

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    {{ t('Color de las cuentas') }}
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="size-9 rounded-full border-2 border-dashed border-input transition-transform hover:scale-105"
                        :class="{
                            'ring-2 ring-ring ring-offset-2': !form.color,
                        }"
                        :aria-label="t('Sin color')"
                        :aria-pressed="!form.color"
                        @click="form.color = null"
                    />
                    <button
                        v-for="color in colors"
                        :key="color.value"
                        type="button"
                        class="mantra-swatch size-9 rounded-full border transition-transform hover:scale-105"
                        :data-color="color.value"
                        :class="{
                            'ring-2 ring-ring ring-offset-2':
                                form.color === color.value,
                        }"
                        :aria-label="color.label"
                        :aria-pressed="form.color === color.value"
                        @click="form.color = color.value"
                    />
                </div>
                <InputError :message="form.errors.color" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="position">{{ t('Orden') }}</Label>
                <Input
                    id="position"
                    v-model="form.position"
                    type="number"
                    min="0"
                    class="max-w-24"
                />
                <InputError :message="form.errors.position" />
            </div>

            <div>
                <Button :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ t('Guardar cambios') }}
                </Button>
            </div>
        </form>

        <div class="space-y-3">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ t('Mantras del retiro') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Se recitan en este orden: terminada la cifra de uno, sigue el siguiente.',
                        )
                    }}
                </p>
            </div>

            <div
                v-for="(stage, index) in stages"
                :key="stage.id"
                class="rounded-xl border p-3"
            >
                <div
                    v-if="editing !== stage.id"
                    class="flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ index + 1 }}. {{ stage.name }}
                        </p>
                        <p
                            class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ stage.text }}
                        </p>
                        <p
                            class="mt-1 text-xs text-muted-foreground tabular-nums"
                        >
                            {{
                                t(':count recitaciones', {
                                    count: stage.goal.toLocaleString('es'),
                                })
                            }}
                            · #{{ stage.position }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            :aria-label="t('Editar')"
                            :title="t('Editar')"
                            @click="startEditing(stage)"
                        >
                            <Pencil class="size-4" />
                        </button>
                        <button
                            type="button"
                            :disabled="stage.in_use"
                            class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40"
                            :aria-label="t('Eliminar')"
                            :title="
                                stage.in_use
                                    ? t('Tiene conteos registrados.')
                                    : t('Eliminar')
                            "
                            @click="destroyStage(stage)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <form v-else class="grid gap-3" @submit.prevent="saveEditing">
                    <div class="grid gap-2">
                        <Label :for="`stage-name-${stage.id}`">
                            {{ t('Nombre') }}
                        </Label>
                        <Input
                            :id="`stage-name-${stage.id}`"
                            v-model="editForm.name"
                            required
                            maxlength="120"
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`stage-text-${stage.id}`">
                            {{ t('Texto') }}
                        </Label>
                        <textarea
                            :id="`stage-text-${stage.id}`"
                            v-model="editForm.text"
                            required
                            :class="textareaClass"
                        />
                        <InputError :message="editForm.errors.text" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <div class="grid gap-2">
                            <Label :for="`stage-goal-${stage.id}`">
                                {{ t('Cantidad') }}
                            </Label>
                            <Input
                                :id="`stage-goal-${stage.id}`"
                                v-model="editForm.goal"
                                type="number"
                                min="1"
                                class="max-w-32"
                            />
                            <InputError :message="editForm.errors.goal" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`stage-position-${stage.id}`">
                                {{ t('Orden') }}
                            </Label>
                            <Input
                                :id="`stage-position-${stage.id}`"
                                v-model="editForm.position"
                                type="number"
                                min="0"
                                class="max-w-24"
                            />
                            <InputError :message="editForm.errors.position" />
                        </div>
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

            <form
                class="grid gap-4 rounded-xl border border-dashed p-4"
                @submit.prevent="addStage"
            >
                <div class="grid gap-2">
                    <Label for="new-stage-name"
                        >{{ t('Mantra nuevo') }} *</Label
                    >
                    <Input
                        id="new-stage-name"
                        v-model="stageForm.name"
                        required
                        maxlength="120"
                        autocomplete="off"
                        :placeholder="
                            t('Por ejemplo: Vajrasattva de cien sílabas')
                        "
                    />
                    <InputError :message="stageForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-stage-text">{{ t('Texto') }} *</Label>
                    <textarea
                        id="new-stage-text"
                        v-model="stageForm.text"
                        required
                        :class="textareaClass"
                        :placeholder="t('OM VAJRA SATTVA SAMAYA…')"
                    />
                    <InputError :message="stageForm.errors.text" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <div class="grid gap-2">
                        <Label for="new-stage-goal"
                            >{{ t('Cantidad') }} *</Label
                        >
                        <Input
                            id="new-stage-goal"
                            v-model="stageForm.goal"
                            type="number"
                            min="1"
                            required
                            class="max-w-32"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ t('Cuántas veces se recita en el retiro.') }}
                        </p>
                        <InputError :message="stageForm.errors.goal" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-stage-position">{{ t('Orden') }}</Label>
                        <Input
                            id="new-stage-position"
                            v-model="stageForm.position"
                            type="number"
                            min="0"
                            class="max-w-24"
                        />
                        <InputError :message="stageForm.errors.position" />
                    </div>
                </div>

                <div>
                    <Button :disabled="stageForm.processing">
                        <Spinner v-if="stageForm.processing" />
                        <Plus v-else class="size-4" />
                        {{ t('Agregar mantra') }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
