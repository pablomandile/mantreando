<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Apariencia')" />

    <h1 class="sr-only">{{ t('Apariencia') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('Apariencia')"
            :description="t('Elegí el tema de la aplicación')"
        />
        <AppearanceTabs />
    </div>
</template>
