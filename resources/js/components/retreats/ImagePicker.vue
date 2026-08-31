<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { Label } from '@/components/ui/label';

interface GalleryImage {
    path: string;
    url: string;
}

/**
 * Elige una imagen: o se sube una nueva, o se reusa una de las que ya están
 * cargadas (la lámina de un mantra del sistema, o la de otra deidad).
 */
const props = defineProps<{
    label: string;
    /** La que tiene guardada hoy. */
    currentUrl: string | null;
    path: string | null;
    gallery: GalleryImage[];
    error?: string;
}>();

const emit = defineEmits<{
    'update:path': [value: string | null];
    'update:file': [value: File | null];
}>();

const file = ref<File | null>(null);
const objectUrl = ref<string | null>(null);
const open = ref(false);

// La URL del archivo recién elegido hay que revocarla o queda colgada en
// memoria mientras dure la pantalla.
watch(file, (value, previous) => {
    if (objectUrl.value !== null) {
        URL.revokeObjectURL(objectUrl.value);
        objectUrl.value = null;
    }

    if (value) {
        objectUrl.value = URL.createObjectURL(value);
    }

    if (value !== previous) {
        emit('update:file', value);
    }
});

const preview = computed<string | null>(() => {
    if (objectUrl.value !== null) {
        return objectUrl.value;
    }

    const chosen = props.gallery.find((image) => image.path === props.path);

    return chosen?.url ?? props.currentUrl;
});

function onFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;

    // Un archivo nuevo pisa la elección de la grilla.
    if (file.value) {
        emit('update:path', null);
        open.value = false;
    }
}

function choose(image: GalleryImage): void {
    file.value = null;
    emit('update:path', image.path);
    open.value = false;
}
</script>

<template>
    <div class="grid gap-2">
        <Label>{{ label }}</Label>

        <div class="flex items-start gap-3">
            <img
                v-if="preview"
                :src="preview"
                :alt="label"
                class="size-24 shrink-0 rounded-lg border object-cover"
            />
            <span
                v-else
                class="flex size-24 shrink-0 items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground"
            >
                {{ t('Sin imagen') }}
            </span>

            <div class="flex min-w-0 flex-col gap-2">
                <button
                    type="button"
                    class="w-fit rounded-md border border-input px-2.5 py-1.5 text-xs transition-colors hover:bg-accent"
                    :aria-expanded="open"
                    @click="open = !open"
                >
                    {{ open ? t('Cerrar') : t('Elegir una ya cargada') }}
                </button>

                <input
                    type="file"
                    accept="image/*"
                    class="max-w-full text-xs text-muted-foreground file:mr-2 file:rounded-md file:border file:border-input file:bg-background file:px-2.5 file:py-1.5 file:text-xs"
                    @change="onFile"
                />
            </div>
        </div>

        <div
            v-if="open"
            class="grid max-h-64 grid-cols-4 gap-2 overflow-y-auto rounded-lg border p-2 sm:grid-cols-6"
        >
            <p
                v-if="gallery.length === 0"
                class="col-span-full py-4 text-center text-xs text-muted-foreground"
            >
                {{ t('No hay imágenes cargadas todavía.') }}
            </p>
            <button
                v-for="image in gallery"
                :key="image.path"
                type="button"
                class="overflow-hidden rounded-md border transition-transform hover:scale-105"
                :class="{ 'ring-2 ring-ring': image.path === path }"
                :aria-pressed="image.path === path"
                @click="choose(image)"
            >
                <img
                    :src="image.url"
                    :alt="image.path"
                    class="aspect-square w-full object-cover"
                />
            </button>
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-500">
            {{ error }}
        </p>
    </div>
</template>
