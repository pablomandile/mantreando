<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
}

const props = defineProps<{
    mantra?: MantraData;
    categories: { id: number; name: string }[];
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
    image: null as File | null,
    remove_image: false,
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
            <Label for="name">Nombre *</Label>
            <Input id="name" v-model="form.name" required placeholder="Ej: Om Mani Padme Hum" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="original_name">Nombre original</Label>
                <Input
                    id="original_name"
                    v-model="form.original_name"
                    placeholder="En tibetano o sánscrito"
                />
                <InputError :message="form.errors.original_name" />
            </div>
            <div class="grid gap-2">
                <Label for="transliteration">Transliteración</Label>
                <Input
                    id="transliteration"
                    v-model="form.transliteration"
                    placeholder="oṃ maṇi padme hūṃ"
                />
                <InputError :message="form.errors.transliteration" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="text">Texto del mantra *</Label>
            <textarea
                id="text"
                v-model="form.text"
                required
                :class="textareaClass"
                placeholder="El texto tal como se recita"
            />
            <InputError :message="form.errors.text" />
        </div>

        <div class="grid gap-2">
            <Label for="translation">Traducción</Label>
            <textarea
                id="translation"
                v-model="form.translation"
                :class="textareaClass"
            />
            <InputError :message="form.errors.translation" />
        </div>

        <div class="grid gap-2">
            <Label for="category_id">Categoría *</Label>
            <select
                id="category_id"
                v-model="form.category_id"
                required
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option :value="null" disabled>Elegí una categoría</option>
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

        <div class="grid gap-2">
            <Label for="description">Descripción</Label>
            <textarea
                id="description"
                v-model="form.description"
                :class="textareaClass"
                placeholder="Origen, deidad asociada, contexto…"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="benefits">Beneficios</Label>
            <textarea
                id="benefits"
                v-model="form.benefits"
                :class="textareaClass"
            />
            <InputError :message="form.errors.benefits" />
        </div>

        <div class="grid gap-2">
            <Label for="image">Imagen</Label>
            <img
                v-if="imagePreview"
                :src="imagePreview"
                alt="Vista previa"
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
                    Quitar
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">JPG/PNG/WebP, máx. 2 MB.</p>
            <InputError :message="form.errors.image" />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ mantra?.id ? 'Guardar cambios' : 'Crear mantra' }}
            </Button>
            <slot name="actions" />
        </div>
    </form>
</template>
