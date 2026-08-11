<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import MantraForm from '@/components/mantras/MantraForm.vue';

defineProps<{
    categories: { id: number; name: string; slug: string }[];
    colors: { value: string; label: string }[];
    canShare: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mantras', href: '/mantras' },
            { title: 'Nuevo', href: '/mantras/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Nuevo mantra')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Nuevo mantra') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Agregá un mantra personal a tu biblioteca.') }}
            </p>
        </div>

        <MantraForm
            :categories="categories"
            :colors="colors"
            :can-share="canShare"
        />
    </div>
</template>
