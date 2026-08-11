<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface MantraData {
    id?: number;
    name: string;
    original_name: string | null;
    transliteration: string | null;
    text: string;
    translation: string | null;
    description: string | null;
    benefits: string | null;
    category_id: number | null;
    image_url?: string | null;
    color?: string | null;
}

const props = defineProps<{
    mantra?: MantraData;
    categories: { id: number; name: string }[];
    colors: { value: string; label: string }[];
    /** Solo los administradores pueden publicar para todos. */
    canShare?: boolean;
    isShared?: boolean;
}>();

// useForm (no <Form>): el upload de imagen necesita FormData y method spoofing.
const form = useForm({
    name: props.mantra?.name ?? '',
    original_name: props.mantra?.original_name ?? '',
    transliteration: props.mantra?.transliteration ?? '',
    text: props.mantra?.text ?? '',
    translation: props.mantra?.translation ?? '',
    description: props.mantra?.description ?? '',
    benefits: props.mantra?.benefits ?? '',
    category_id: props.mantra?.category_id ?? null,
    color: props.mantra?.color ?? null,
    image: null as File | null,
    remove_image: false,
    // Arranca apagado a propósito: publicar a toda la base es un clic
    // deliberado, no el default de un formulario.
    is_shared: props.isShared ?? false,
});

const imagePreview = ref<string | null>(props.mantra?.image_url ?? null);

function onImageChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
}

function removeImage(): void {
    form.image = null;
    form.remove_image = true;
    imagePreview.value = null;
}

function submit(): void {
    if (props.mantra?.id) {
        // PUT con archivos: Inertia exige POST + _method spoofing.
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/mantras/${props.mantra.id}`,
            { forceFormData: true },
        );
    } else {
        form.post('/mantras', { forceFormData: true });
    }
}

const textareaClass =
    'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">{{ t('Nombre') }} *</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                placeholder="Om Mani Padme Hum"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="original_name">{{ t('Nombre original') }}</Label>
                <Input
                    id="original_name"
                    v-model="form.original_name"
                    :placeholder="t('En tibetano o sánscrito')"
                />
                <InputError :message="form.errors.original_name" />
            </div>
            <div class="grid gap-2">
                <Label for="transliteration">{{ t('Transliteración') }}</Label>
                <Input
                    id="transliteration"
                    v-model="form.transliteration"
                    placeholder="oṃ maṇi padme hūṃ"
                />
                <InputError :message="form.errors.transliteration" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="text">{{ t('Texto del mantra') }} *</Label>
            <textarea
                id="text"
                v-model="form.text"
                required
                :class="textareaClass"
                :placeholder="t('El texto tal como se recita')"
            />
            <InputError :message="form.errors.text" />
        </div>

        <div class="grid gap-2">
            <Label for="translation">{{ t('Traducción') }}</Label>
            <textarea
                id="translation"
                v-model="form.translation"
                :class="textareaClass"
            />
            <InputError :message="form.errors.translation" />
        </div>

        <div class="grid gap-2">
            <Label for="category_id">{{ t('Categoría') }} *</Label>
            <select
                id="category_id"
                v-model="form.category_id"
                required
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option :value="null" disabled>
                    {{ t('Elegí una categoría') }}
                </option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>
            <InputError :message="form.errors.category_id" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">
                {{ t('Color de la tarjeta') }}
            </legend>
            <p class="text-xs text-muted-foreground">
                {{ t('Elegí un color y el degradado se arma solo.') }}
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="size-9 rounded-full border-2 border-dashed border-input transition-transform hover:scale-105"
                    :class="{ 'ring-2 ring-ring ring-offset-2': !form.color }"
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
            <Label for="description">{{ t('Descripción') }}</Label>
            <textarea
                id="description"
                v-model="form.description"
                :class="textareaClass"
                :placeholder="t('Origen, deidad asociada, contexto…')"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="benefits">{{ t('Beneficios') }}</Label>
            <textarea
                id="benefits"
                v-model="form.benefits"
                :class="textareaClass"
            />
            <InputError :message="form.errors.benefits" />
        </div>

        <div class="grid gap-2">
            <Label for="image">{{ t('Imagen') }}</Label>
            <img
                v-if="imagePreview"
                :src="imagePreview"
                :alt="t('Vista previa')"
                class="h-32 w-32 rounded-lg object-cover"
            />
            <div class="flex items-center gap-2">
                <input
                    id="image"
                    type="file"
                    accept="image/*"
                    class="text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                    @change="onImageChange"
                />
                <Button
                    v-if="imagePreview"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="removeImage"
                >
                    {{ t('Quitar') }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ t('JPG/PNG/WebP, máx. 2 MB.') }}
            </p>
            <InputError :message="form.errors.image" />
        </div>

        <!-- Solo para administradores: el resto de las cuentas no ve el campo
             y el servidor lo ignora si llegara igual. -->
        <div v-if="canShare" class="grid gap-2 rounded-xl border p-4">
            <Label class="flex items-start gap-3">
                <Checkbox
                    :model-value="form.is_shared"
                    class="mt-0.5"
                    @update:model-value="
                        (value: boolean | 'indeterminate') =>
                            (form.is_shared = value === true)
                    "
                />
                <span>
                    {{ t('Visible para todos') }}
                    <span
                        class="block text-xs font-normal text-muted-foreground"
                    >
                        {{
                            t(
                                'Pasa a ser un mantra del sistema: aparece en la biblioteca de todas las cuentas. En la práctica sin conexión aparece cuando el dispositivo vuelve a sincronizar.',
                            )
                        }}
                    </span>
                </span>
            </Label>
            <InputError :message="form.errors.is_shared" />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ mantra?.id ? t('Guardar cambios') : t('Crear mantra') }}
            </Button>
            <slot name="actions" />
        </div>
    </form>
</template>
