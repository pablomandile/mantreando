<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Estadísticas', href: '/stats' }],
    },
});
</script>

<template>
    <Head title="Estadísticas" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Estadísticas</h1>
        <p
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            El panel de progreso llega en la Etapa 7: recitaciones, tiempo de
            práctica, rachas y objetivos.
        </p>
    </div>
</template>
