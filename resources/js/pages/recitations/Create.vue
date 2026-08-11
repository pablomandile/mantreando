<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import RecitationForm from '@/components/recitations/RecitationForm.vue';

defineProps<{
    nextPosition: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Otras recitaciones', href: '/recitations' },
            { title: 'Nueva', href: '/recitations/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Nueva recitación')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Nueva recitación') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Queda disponible para todas las cuentas.') }}
            </p>
        </div>

        <RecitationForm :next-position="nextPosition" />
    </div>
</template>
