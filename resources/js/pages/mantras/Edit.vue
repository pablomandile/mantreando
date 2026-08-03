<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
    };
    categories: { id: number; name: string; slug: string }[];
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
    if (confirm('¿Eliminar este mantra? Esta acción no se puede deshacer.')) {
        router.delete(`/mantras/${props.mantra.id}`);
    }
}
</script>

<template>
    <Head :title="`Editar ${mantra.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">Editar mantra</h1>
            <p class="text-sm text-muted-foreground">{{ mantra.name }}</p>
        </div>

        <MantraForm :mantra="mantra" :categories="categories">
            <template #actions>
                <Button type="button" variant="destructive" @click="destroy">
                    Eliminar
                </Button>
            </template>
        </MantraForm>
    </div>
</template>
