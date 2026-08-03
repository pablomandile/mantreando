<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

/**
 * Personalización del mala: material (4 paletas) y textura propia opcional.
 * El preset activo viaja a la isla vía el bootstrap y se aplica en la
 * pantalla de práctica.
 */

const props = defineProps<{
    preset: {
        material: 'wood' | 'bodhi' | 'red' | 'blue';
        texture_url: string | null;
    };
    materials: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi mala', href: '/settings/mala' }],
    },
});

const MATERIAL_LABELS = computed<Record<string, string>>(() => ({
    wood: t('Madera'),
    bodhi: t('Semilla de Bodhi'),
    red: t('Degradado rojo'),
    blue: t('Degradado azul'),
}));

const MATERIAL_SWATCHES: Record<string, string> = {
    wood: 'radial-gradient(circle at 35% 30%, #d3a878, #9a6b42 55%, #5e3a21)',
    bodhi: 'radial-gradient(circle at 35% 30%, #f2e7cd, #cdb289 55%, #8a6f47)',
    red: 'radial-gradient(circle at 35% 30%, #e8907e, #b93a2b 55%, #711b10)',
    blue: 'radial-gradient(circle at 35% 30%, #93bbe9, #3b6fb5 55%, #1c3d6b)',
};

const material = ref(props.preset.material);
const texturePreview = ref<string | null>(props.preset.texture_url);
const textureFile = ref<File | null>(null);
const removeTexture = ref(false);
const saving = ref(false);

function onTextureChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    textureFile.value = file;
    removeTexture.value = false;
    texturePreview.value = file ? URL.createObjectURL(file) : null;
}

function clearTexture(): void {
    textureFile.value = null;
    removeTexture.value = true;
    texturePreview.value = null;
}

function save(): void {
    saving.value = true;
    router.post(
        '/settings/mala',
        {
            material: material.value,
            texture: textureFile.value,
            remove_texture: removeTexture.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Head :title="t('Mi mala')" />

    <h1 class="sr-only">{{ t('Personalización del mala') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('Mi mala')"
            :description="t('El material y la textura de tus cuentas')"
        />

        <div class="grid gap-3">
            <p class="text-sm font-medium">{{ t('Material') }}</p>
            <div class="flex flex-wrap gap-3">
                <button
                    v-for="key in materials"
                    :key="key"
                    type="button"
                    class="flex flex-col items-center gap-2 rounded-xl border p-3 transition-colors"
                    :class="{
                        'border-foreground': material === key,
                        'hover:bg-accent': material !== key,
                    }"
                    :aria-pressed="material === key"
                    @click="material = key as typeof material"
                >
                    <span
                        class="size-12 rounded-full shadow-inner"
                        :style="{ background: MATERIAL_SWATCHES[key] }"
                    />
                    <span class="text-xs">{{ MATERIAL_LABELS[key] ?? key }}</span>
                </button>
            </div>
        </div>

        <div class="grid gap-2">
            <p class="text-sm font-medium">{{ t('Textura propia') }}</p>
            <p class="text-xs text-muted-foreground">
                {{
                    t(
                        'Una imagen tuya como superficie de las cuentas (reemplaza al material). JPG/PNG/WebP, máx. 2 MB. Es privada: solo vos la ves.',
                    )
                }}
            </p>
            <div class="flex items-center gap-3">
                <span
                    v-if="texturePreview"
                    class="size-12 rounded-full bg-cover bg-center shadow-inner"
                    :style="{ backgroundImage: `url(${texturePreview})` }"
                />
                <input
                    type="file"
                    accept="image/*"
                    class="text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:text-foreground"
                    @change="onTextureChange"
                />
                <Button
                    v-if="texturePreview"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="clearTexture"
                >
                    {{ t('Quitar') }}
                </Button>
            </div>
        </div>

        <Button :disabled="saving" @click="save">{{ t('Guardar') }}</Button>
    </div>
</template>
