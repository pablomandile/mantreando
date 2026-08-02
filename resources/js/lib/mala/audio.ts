/**
 * Sonido del mala, 100% sintetizado con WebAudio: cero assets, cero
 * licencias. El click "de madera" es una ráfaga de ruido por un bandpass
 * de ~1.8 kHz con decay rápido más un seno grave de 180 Hz (el cuerpo).
 * Un sample grabado CC0 es tarea de sound design de la Etapa 8.
 *
 * iOS/Chrome exigen que el AudioContext se cree/resuma dentro de un gesto
 * del usuario: llamar unlockAudio() en el primer pointerdown.
 */

let context: AudioContext | null = null;
let noiseBuffer: AudioBuffer | null = null;
let enabled = false; // opt-in: el sonido arranca apagado

export function setSoundEnabled(value: boolean): void {
    enabled = value;
}

export function isSoundEnabled(): boolean {
    return enabled;
}

/** Crear/resumir el contexto dentro de un gesto (primer pointerdown). */
export function unlockAudio(): void {
    if (typeof window === 'undefined' || !('AudioContext' in window)) {
        return;
    }

    context ??= new AudioContext();

    if (context.state === 'suspended') {
        void context.resume();
    }
}

function getNoiseBuffer(ctx: AudioContext): AudioBuffer {
    if (noiseBuffer === null) {
        const length = Math.floor(ctx.sampleRate * 0.03); // 30 ms
        noiseBuffer = ctx.createBuffer(1, length, ctx.sampleRate);
        const data = noiseBuffer.getChannelData(0);

        for (let i = 0; i < length; i++) {
            data[i] = Math.random() * 2 - 1;
        }
    }

    return noiseBuffer;
}

function playClick(frequency: number, bodyHz: number, gain: number): void {
    if (!enabled || context === null || context.state !== 'running') {
        return;
    }

    const ctx = context;
    const now = ctx.currentTime;

    // Golpe: ruido filtrado con decay exponencial rápido
    const noise = ctx.createBufferSource();
    noise.buffer = getNoiseBuffer(ctx);

    const bandpass = ctx.createBiquadFilter();
    bandpass.type = 'bandpass';
    bandpass.frequency.value = frequency;
    bandpass.Q.value = 4;

    const noiseGain = ctx.createGain();
    noiseGain.gain.setValueAtTime(gain, now);
    noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 0.03);

    noise.connect(bandpass).connect(noiseGain).connect(ctx.destination);
    noise.start(now);
    noise.stop(now + 0.03);

    // Cuerpo: seno grave y breve
    const body = ctx.createOscillator();
    body.type = 'sine';
    body.frequency.value = bodyHz;

    const bodyGain = ctx.createGain();
    bodyGain.gain.setValueAtTime(gain * 0.5, now);
    bodyGain.gain.exponentialRampToValueAtTime(0.001, now + 0.02);

    body.connect(bodyGain).connect(ctx.destination);
    body.start(now);
    body.stop(now + 0.02);
}

/** Click de cuenta. */
export function soundTick(): void {
    playClick(1800, 180, 0.25);
}

/** Golpe más grave y presente para el gurú / vuelta completa. */
export function soundGuru(): void {
    playClick(900, 120, 0.4);
}
