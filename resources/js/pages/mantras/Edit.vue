<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import MantraForm from '@/components/mantras/MantraForm.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    mantra: {
        id: number;
        name: string;
        original_name: string | null;
        transliteration: string | null;
        text: string;
        translation: string | null;
        description: string | null;
        benefits: string | null;
        category_id: number;
        image_url: string | null;
        color: string | null;
    };
    categories: { id: number; name: string; slug: string }[];
    colors: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mantras', href: '/mantras' },
            { title: 'Editar', href: '' },
        ],
    },
});

function destroy(): void {
    if (confirm(t('¿Eliminar este mantra? Esta acción no se puede deshacer.'))) {
        router.delete(`/mantras/${props.mantra.id}`);
    }
}
</script>

<template>
    <Head :title="`${t('Editar mantra')} — ${mantra.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Editar mantra') }}</h1>
            <p class="text-sm text-muted-foreground">{{ mantra.name }}</p>
        </div>

        <MantraForm :mantra="mantra" :categories="categories" :colors="colors">
            <template #actions>
                <Button type="button" variant="destructive" @click="destroy">
                    {{ t('Eliminar') }}
                </Button>
            </template>
        </MantraForm>
    </div>
</template>
