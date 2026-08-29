<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import PrayerIntentionForm from '@/components/prayers/PrayerIntentionForm.vue';

defineProps<{
    reasons: { id: number; name: string; color: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lista de oración', href: '/prayers' },
            { title: 'Agregar', href: '/prayers/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Agregar a la lista de oración')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Agregar a la lista') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Solo vos ves tu lista de oración.') }}
            </p>
        </div>

        <PrayerIntentionForm :reasons="reasons" />
    </div>
</template>
