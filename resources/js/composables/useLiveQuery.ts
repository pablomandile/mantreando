import { liveQuery } from 'dexie';
import { onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Adaptador mínimo Dexie liveQuery → ref de Vue: la UI de la isla se
 * re-renderiza sola cuando cambia IndexedDB (sync, outbox, etc.).
 */
export function useLiveQuery<T>(querier: () => Promise<T>, initial: T): Ref<T> {
    const value = ref(initial) as Ref<T>;

    const subscription = liveQuery(querier).subscribe({
        next: (result) => {
            value.value = result;
        },
        error: () => {
            // IndexedDB no disponible (p. ej. modo privado antiguo): la UI
            // queda con el valor inicial; el server-side sigue funcionando.
        },
    });

    onUnmounted(() => subscription.unsubscribe());

    return value;
}
