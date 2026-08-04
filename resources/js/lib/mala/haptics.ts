/**
 * Feedback háptico del mala.
 *
 * - Web: Vibration API. No existe en iOS Safari (ni como PWA): se detecta
 *   y degrada en silencio — la UI oculta el toggle cuando no hay soporte.
 * - Nativo (Capacitor): el WebView inyecta window.Capacitor y usamos el
 *   plugin Haptics por el bridge global — sin imports, así el bundle web
 *   no carga nada extra. Esto resuelve la limitación de iOS en la app
 *   nativa (Etapa 12 del plan).
 */

interface CapacitorGlobal {
    isNativePlatform?: () => boolean;
    Plugins?: {
        Haptics?: {
            impact(options: {
                style: 'HEAVY' | 'MEDIUM' | 'LIGHT';
            }): Promise<void>;
            vibrate(options: { duration: number }): Promise<void>;
        };
    };
}

function capacitorHaptics() {
    const capacitor = (globalThis as { Capacitor?: CapacitorGlobal }).Capacitor;

    return capacitor?.isNativePlatform?.()
        ? capacitor.Plugins?.Haptics
        : undefined;
}

export const hapticsSupported =
    (typeof navigator !== 'undefined' && 'vibrate' in navigator) ||
    capacitorHaptics() !== undefined;

let enabled = true;

export function setHapticsEnabled(value: boolean): void {
    enabled = value;
}

export function isHapticsEnabled(): boolean {
    return enabled;
}

/** Pulso breve al pasar una cuenta. */
export function hapticTick(): void {
    if (!enabled) {
        return;
    }

    const native = capacitorHaptics();

    if (native) {
        void native.impact({ style: 'LIGHT' });

        return;
    }

    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(10);
    }
}

/** Patrón distintivo al tocar el gurú / completar la vuelta. */
export function hapticGuru(): void {
    if (!enabled) {
        return;
    }

    const native = capacitorHaptics();

    if (native) {
        void native.impact({ style: 'MEDIUM' });
        setTimeout(() => void native.impact({ style: 'HEAVY' }), 60);

        return;
    }

    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate([15, 40, 30]);
    }
}
