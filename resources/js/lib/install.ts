import { computed, readonly, ref } from 'vue';

/**
 * Instalación de la PWA (prompt propio).
 *
 * El navegador dispara `beforeinstallprompt` UNA sola vez y temprano: si no
 * hay listener en ese momento el evento se pierde y ya no hay forma de
 * ofrecer la instalación. Por eso el listener se registra en el arranque
 * (app.ts) y el evento queda guardado en este módulo, no en el componente
 * del botón: el botón puede montarse mucho después.
 *
 * iOS/iPadOS no implementa `beforeinstallprompt` — la instalación es manual
 * (Compartir → Añadir a pantalla de inicio), así que ahí el botón muestra
 * instrucciones en vez de disparar el prompt nativo.
 */

/** El evento no está en lib.dom: Chromium lo expone, el estándar no. */
type BeforeInstallPromptEvent = Event & {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const installed = ref(false);

/** Ya corriendo como app instalada (no tiene sentido ofrecer instalar). */
function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        // iOS marca las apps de pantalla de inicio acá, no en display-mode.
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/**
 * iPadOS 13+ se hace pasar por Macintosh en el userAgent; se distingue por
 * los puntos de contacto (una Mac con trackpad reporta 0).
 */
function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const ua = navigator.userAgent;

    return (
        /iPad|iPhone|iPod/.test(ua) ||
        (ua.includes('Macintosh') && navigator.maxTouchPoints > 1)
    );
}

export function initializeInstallPrompt(): void {
    if (typeof window === 'undefined') {
        return;
    }

    installed.value = isStandalone();

    window.addEventListener('beforeinstallprompt', (event) => {
        // Sin preventDefault, Chrome muestra su propio mini-infobar y el
        // evento no queda disponible para el botón.
        event.preventDefault();
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        installed.value = true;
        deferredPrompt.value = null;
    });
}

export function useInstallPrompt() {
    const ios = isIosDevice();

    return {
        installed: readonly(installed),
        /** iOS necesita instrucciones: no hay prompt programático. */
        needsManualSteps: computed(() => ios && !installed.value),
        /**
         * El botón aparece solo si hay algo que ofrecer: el prompt nativo
         * (Chromium) o las instrucciones de iOS. En un navegador que no
         * soporta instalar (Firefox de escritorio) queda oculto.
         */
        canInstall: computed(
            () => !installed.value && (deferredPrompt.value !== null || ios),
        ),
        /** true si el usuario aceptó. Sin prompt disponible, false. */
        async promptInstall(): Promise<boolean> {
            const event = deferredPrompt.value;

            if (!event) {
                return false;
            }

            await event.prompt();
            const { outcome } = await event.userChoice;

            // El evento es de un solo uso: si lo rechazó, el navegador
            // volverá a dispararlo en una visita futura.
            deferredPrompt.value = null;

            return outcome === 'accepted';
        },
    };
}
