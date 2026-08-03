<script setup lang="ts">
import { Star } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { useLiveQuery } from '@/composables/useLiveQuery';
import { db } from '@/lib/practice/db';
import type { CachedMantra } from '@/lib/practice/types';

/**
 * Lista de mantras de la ISLA: lee exclusivamente de IndexedDB (cache del
 * bootstrap), nunca de props del servidor. Con la cache poblada, esta vista
 * funciona sin conexión.
 */

const mantras = useLiveQuery<CachedMantra[]>(
    () => db.mantras.orderBy('name').toArray(),
    [],
);

const emit = defineEmits<{
    select: [mantra: CachedMantra];
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <p
            v-if="mantras.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{
                t(
                    'Todavía no hay mantras en la memoria local. Conectate una vez para descargar tu biblioteca.',
                )
            }}
        </p>

        <button
            v-for="mantra in mantras"
            :key="mantra.id"
            type="button"
            class="group flex flex-col gap-1 rounded-xl border border-sidebar-border/70 p-4 text-left transition-colors hover:bg-accent dark:border-sidebar-border"
            @click="emit('select', mantra)"
        >
            <span class="flex items-center justify-between">
                <span class="font-medium">{{ mantra.name }}</span>
                <Star
                    v-if="mantra.pivot.is_favorite"
                    class="size-4 fill-amber-400 text-amber-400"
                />
            </span>
            <span class="text-sm text-muted-foreground">
                {{ mantra.text }}
            </span>
            <span
                v-if="mantra.category"
                class="mt-1 w-fit rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
            >
                {{ mantra.category.name }}
            </span>
        </button>
    </div>
</template>
