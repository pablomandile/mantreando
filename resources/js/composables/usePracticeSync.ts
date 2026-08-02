import { onMounted, onUnmounted } from 'vue';
import { syncAll } from '@/lib/practice/sync';

/**
 * Cablea el sincronizador de la isla a los triggers del navegador:
 * carga de la página, evento 'online' y vuelta a primer plano.
 * (Background Sync del service worker llega en la etapa PWA.)
 */
export function usePracticeSync(): void {
    const onOnline = () => void syncAll();
    const onVisible = () => {
        if (document.visibilityState === 'visible') {
            void syncAll();
        }
    };

    onMounted(() => {
        void syncAll();
        window.addEventListener('online', onOnline);
        document.addEventListener('visibilitychange', onVisible);
    });

    onUnmounted(() => {
        window.removeEventListener('online', onOnline);
        document.removeEventListener('visibilitychange', onVisible);
    });
}
