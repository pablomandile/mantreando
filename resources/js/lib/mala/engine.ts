import type { Direction, MalaEvent, MalaMode, MalaSnapshot, PositionBounds } from './types';
import { ADVANCE_LOCK_MS, BEAD_COUNT, GURU_SLOT, GURU_TRIGGER, mod, SLOT_COUNT, slotAt } from './types';

/**
 * Motor de conteo del mala. TypeScript puro, sin Vue: la física le alimenta
 * la posición continua cada frame y el motor emite eventos discretos
 * (bead/guru/reverse/completed) a los que se suscriben la UI, el feedback
 * háptico/sonoro y — en la Etapa 5 — el registrador de sesiones.
 *
 * Modo TRADICIONAL — segmento acotado [0, 107]:
 *   Por la periodicidad 109 el gurú queda en ambos extremos
 *   (slot(-1) = slot(108) = gurú). Cuenta al LLEGAR a cada cuenta en la
 *   dirección actual (1..107); el gesto 108 empuja contra el gurú
 *   (overshoot ≥ GURU_TRIGGER) → cuenta 108 + completed + reverse.
 *   El gurú nunca se cruza; la dirección se invierte, como en la práctica
 *   tradicional. Exactamente 108 por vuelta, sin off-by-one.
 *
 * Modo ASISTIDO — posición sin límites, render mod-109 (hebra infinita):
 *   Conteo por HIGH-WATER MARK: la cuenta k se cuenta cuando el máximo
 *   histórico de la posición cruza el punto medio k − 0.5. Retroceder nunca
 *   descuenta y re-cruzar el mismo punto medio no puede contar doble — esto
 *   ES el debounce de gestos continuos, sin timers. Pasar el slot gurú emite
 *   'guru' sin contar.
 */
export class MalaEngine {
    private mode: MalaMode;
    private count = 0;
    private round = 0;
    private totalCount = 0;
    private direction: Direction = 1;
    private position = 0;

    /**
     * Extremo de la posición en la dirección de conteo:
     * asistido → máximo histórico; tradicional → máximo desde el último
     * reverse (dir +1) o mínimo desde el último reverse (dir −1).
     */
    private extremum = 0;

    /** true mientras el empuje actual contra el gurú ya disparó la 108. */
    private guruLatched = false;

    private lastAdvanceAt = -Infinity;

    private listeners = new Set<(event: MalaEvent) => void>();

    constructor(opts: { mode: MalaMode }) {
        this.mode = opts.mode;
    }

    getSnapshot(): MalaSnapshot {
        return {
            mode: this.mode,
            count: this.count,
            round: this.round,
            totalCount: this.totalCount,
            direction: this.direction,
            position: this.position,
            restSlot: slotAt(this.position),
        };
    }

    /** La física alimenta esto cada frame (unidades de cuenta, float). */
    setPosition(position: number): void {
        this.position = position;

        if (this.mode === 'assisted') {
            this.trackAssisted(position);
        } else {
            this.trackTraditional(position);
        }
    }

    /**
     * Avance discreto (tap, solo asistido). Devuelve false durante el lock
     * anti doble-toque o en modo tradicional (el tap no es parte del ritual).
     */
    advance(now: number = Date.now()): boolean {
        if (this.mode !== 'assisted') {
            return false;
        }

        if (now - this.lastAdvanceAt < ADVANCE_LOCK_MS) {
            return false;
        }

        this.lastAdvanceAt = now;
        // Mover la posición al siguiente reposo dispara el conteo normal
        // por high-water mark (la física anima hacia este mismo target).
        this.setPosition(Math.round(this.position) + 1);

        return true;
    }

    /** Tradicional: {0, 107} fijos. Asistido: null (loop sin límites). */
    bounds(): PositionBounds | null {
        return this.mode === 'traditional' ? { min: 0, max: BEAD_COUNT - 1 } : null;
    }

    slotAt(position: number): number {
        return slotAt(position);
    }

    subscribe(listener: (event: MalaEvent) => void): () => void {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    setMode(mode: MalaMode): void {
        this.mode = mode;
        this.resetState();
    }

    reset(): void {
        this.resetState();
        this.emit({ type: 'reset' });
    }

    // ── internos ────────────────────────────────────────────────────────────

    private resetState(): void {
        this.count = 0;
        this.round = 0;
        this.totalCount = 0;
        this.direction = 1;
        this.position = 0;
        this.extremum = 0;
        this.guruLatched = false;
        this.lastAdvanceAt = -Infinity;
    }

    private emit(event: MalaEvent): void {
        for (const listener of this.listeners) {
            listener(event);
        }
    }

    private registerCount(): void {
        this.count += 1;
        this.totalCount += 1;
        this.emit({
            type: 'bead',
            count: this.count,
            totalCount: this.totalCount,
            direction: this.direction,
        });

        if (this.count >= BEAD_COUNT) {
            this.round += 1;
            this.emit({
                type: 'completed',
                round: this.round,
                totalCount: this.totalCount,
            });
            this.count = 0;
        }
    }

    /**
     * Asistido: cuenta cada punto medio k − 0.5 que el máximo histórico
     * cruza hacia adelante. slot(k) == gurú emite 'guru' sin contar.
     */
    private trackAssisted(position: number): void {
        if (position <= this.extremum) {
            return; // retroceso o quieto: nunca descuenta
        }

        // Cuentas k cuyo punto medio k − 0.5 quedó en (extremum, position]:
        // intervalo semiabierto — estricto en extremum (ya contado) e
        // inclusivo en position, para no duplicar cuando un frame cae
        // exactamente sobre un punto medio.
        const firstBead = Math.floor(this.extremum + 0.5) + 1;
        const lastBead = Math.floor(position + 0.5);

        for (let k = firstBead; k <= lastBead; k++) {
            if (mod(k, SLOT_COUNT) === GURU_SLOT) {
                this.emit({ type: 'guru' });
            } else {
                this.registerCount();
            }
        }

        this.extremum = position;
    }

    /**
     * Tradicional: llegada a cada cuenta del segmento [0, 107] en la
     * dirección actual; el empuje contra el gurú (overshoot ≥ GURU_TRIGGER)
     * dispara la cuenta 108 + completed + reverse. Latch hasta volver a
     * territorio normal para que el rebote no re-dispare.
     */
    private trackTraditional(position: number): void {
        const max = BEAD_COUNT - 1; // 107

        if (this.direction === 1) {
            if (position > this.extremum) {
                // Semiabierto (extremum, position] — ver trackAssisted.
                const firstBead = Math.floor(this.extremum + 0.5) + 1;
                const lastBead = Math.min(Math.floor(position + 0.5), max);

                for (let k = firstBead; k <= lastBead; k++) {
                    this.registerCount();
                }

                this.extremum = position;
            }

            if (!this.guruLatched && position >= max + GURU_TRIGGER) {
                this.touchGuru();
            }
        } else {
            if (position < this.extremum) {
                // Espejo del ascenso: semiabierto [position, extremum).
                const firstBead = Math.ceil(this.extremum - 0.5) - 1;
                const lastBead = Math.max(Math.ceil(position - 0.5), 0);

                for (let k = firstBead; k >= lastBead; k--) {
                    this.registerCount();
                }

                this.extremum = position;
            }

            if (!this.guruLatched && position <= -GURU_TRIGGER) {
                this.touchGuru();
            }
        }

        // El latch se libera cuando la física asentó de vuelta en el segmento.
        if (this.guruLatched && position > -GURU_TRIGGER && position < max + GURU_TRIGGER) {
            this.guruLatched = false;
        }
    }

    private touchGuru(): void {
        this.guruLatched = true;
        this.emit({ type: 'guru' });
        this.registerCount(); // la 108 → dispara completed adentro

        this.direction = (this.direction === 1 ? -1 : 1) as Direction;
        this.extremum = this.position;
        this.emit({ type: 'reverse', direction: this.direction });
    }
}
