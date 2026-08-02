/**
 * Feedback háptico del mala. La Vibration API no existe en iOS Safari
 * (ni como PWA): se detecta y degrada en silencio — el toggle de la UI
 * debería ocultarse cuando `hapticsSupported` es false. Capacitor lo
 * resuelve nativo en la etapa Android.
 */

export const hapticsSupported =
    typeof navigator !== 'undefined' && 'vibrate' in navigator;

let enabled = true;

export function setHapticsEnabled(value: boolean): void {
    enabled = value;
}

export function isHapticsEnabled(): boolean {
    return enabled;
}

/** Pulso breve al pasar una cuenta. */
export function hapticTick(): void {
    if (enabled && hapticsSupported) {
        navigator.vibrate(10);
    }
}

/** Patrón distintivo al tocar el gurú / completar la vuelta. */
export function hapticGuru(): void {
    if (enabled && hapticsSupported) {
        navigator.vibrate([15, 40, 30]);
    }
}
