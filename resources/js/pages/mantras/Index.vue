<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mantras', href: '/mantras' }],
    },
});
</script>

<template>
    <Head title="Mantras" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Mantras</h1>
        <p
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            La biblioteca de mantras llega en la Etapa 3: creación, categorías,
            favoritos y búsqueda.
        </p>
    </div>
</template>
