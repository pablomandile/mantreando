<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import MantraList from '@/components/practice/MantraList.vue';
import { Button } from '@/components/ui/button';
import { useLiveQuery } from '@/composables/useLiveQuery';
import { usePracticeSync } from '@/composables/usePracticeSync';
import { db } from '@/lib/practice/db';
import { syncAll } from '@/lib/practice/sync';
import type { CachedMantra } from '@/lib/practice/types';

/**
 * Selector de práctica: shell Inertia fino, datos desde IndexedDB.
 * Elegir un mantra abre la pantalla de práctica inmersiva.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Práctica', href: '/practice' }],
    },
});

usePracticeSync();

const outboxCount = useLiveQuery(() => db.outbox.count(), 0);

function startPractice(mantra: CachedMantra): void {
    router.visit(`/practice/session/${mantra.id}`);
}
</script>

<template>
    <Head :title="t('Práctica')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">{{ t('Práctica') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('Elegí un mantra para comenzar.') }}
                </p>
            </div>

            <span
                v-if="outboxCount > 0"
                class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                :title="t('Sesiones pendientes de sincronizar')"
            >
                {{ t(':count sin sincronizar', { count: String(outboxCount) }) }}
            </span>
        </header>

        <MantraList @select="startPractice" />

        <Button
            v-if="outboxCount > 0"
            variant="outline"
            size="sm"
            class="mx-auto"
            @click="() => void syncAll()"
        >
            {{ t('Sincronizar ahora') }}
        </Button>
    </div>
</template>
