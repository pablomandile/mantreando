<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MantraList from '@/components/practice/MantraList.vue';
import { Button } from '@/components/ui/button';
import { useLiveQuery } from '@/composables/useLiveQuery';
import { usePracticeSync } from '@/composables/usePracticeSync';
import { db } from '@/lib/practice/db';
import { getLocalDate, newSessionUuid } from '@/lib/practice/localDate';
import { enqueueSession, syncAll } from '@/lib/practice/sync';
import type { CachedMantra, CachedUser } from '@/lib/practice/types';

/**
 * Shell Inertia FINO de la isla de práctica: cero props de datos del
 * servidor. Todo lo que se ve sale de IndexedDB; la página funciona
 * offline una vez cargada la cache.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Práctica', href: '/practice' }],
    },
});

usePracticeSync();

const outboxCount = useLiveQuery(() => db.outbox.count(), 0);

// ── Botón de sesión de prueba ──────────────────────────────────────────────
// TEMPORAL (se elimina en la Etapa 5, cuando la práctica real registre
// sesiones): demuestra el circuito outbox → sync → MySQL end-to-end.
async function recordTestSession(mantra: CachedMantra) {
    const userMeta = await db.meta.get('user');
    const user = userMeta?.value as CachedUser | undefined;

    const now = new Date();
    const startedAt = new Date(now.getTime() - 60_000);

    await enqueueSession({
        uuid: newSessionUuid(),
        mantra_id: mantra.id,
        mode: 'assisted',
        recitations: 108,
        completed_malas: 1,
        started_at: startedAt.toISOString(),
        ended_at: now.toISOString(),
        duration_seconds: 60,
        local_date: getLocalDate(user?.timezone ?? undefined, now),
    });

    void syncAll();
}
</script>

<template>
    <Head title="Práctica" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Práctica</h1>
                <p class="text-sm text-muted-foreground">
                    Elegí un mantra para comenzar.
                </p>
            </div>

            <span
                v-if="outboxCount > 0"
                class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                title="Sesiones pendientes de sincronizar"
            >
                {{ outboxCount }} sin sincronizar
            </span>
        </header>

        <MantraList @select="recordTestSession" />

        <!-- TEMPORAL Etapa 1: tocar un mantra encola una sesión de prueba. -->
        <p class="text-center text-xs text-muted-foreground">
            Modo de prueba: tocar un mantra registra una sesión de 108
            recitaciones (se reemplaza por el mala en la Etapa 5).
        </p>

        <Button
            variant="outline"
            size="sm"
            class="mx-auto"
            @click="() => void syncAll()"
        >
            Sincronizar ahora
        </Button>
    </div>
</template>
