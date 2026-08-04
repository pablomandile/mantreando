import type { MalaMode, PositionBounds } from './types';

/**
 * Física del strand vertical. TypeScript puro, sin Vue ni DOM: recibe eventos
 * de puntero como (y, t) y expone tick(now) para que el composable la avance
 * desde su único loop de requestAnimationFrame.
 *
 * Unidades: internamente TODO en px (natural para el dedo); la conversión a
 * unidades de cuenta ocurre en una sola frontera (onChange → px / pitch).
 *
 * Máquina de estados: idle → dragging → momentum → snapping → idle
 *  - dragging: la hebra sigue al dedo 1:1. En tradicional, clamp duro a
 *    ±1 pitch del reposo inicial (máx. una cuenta por gesto) y rubber-band
 *    contra los límites del segmento (el empuje al gurú).
 *  - momentum: solo asistido; fricción exponencial frame-rate-independiente.
 *  - snapping: easing exponencial interrumpible al reposo más cercano.
 */

export type PhysicsState = 'idle' | 'dragging' | 'momentum' | 'snapping';

/** Umbral de flick al soltar (px/ms). */
export const FLICK_MIN_VELOCITY = 0.3;
/** Velocidad bajo la cual el momentum termina (px/ms). */
export const MOMENTUM_MIN_VELOCITY = 0.05;
/** Decaimiento de velocidad por frame de 16.67 ms. */
export const FRICTION = 0.95;
/** Ventana de muestreo de velocidad al soltar (ms). */
export const VELOCITY_WINDOW_MS = 100;
/** Tap: movimiento y duración máximos. */
export const TAP_MAX_MOVEMENT_PX = 8;
export const TAP_MAX_DURATION_MS = 250;
/**
 * Rigidez del rubber-band. Con el clamp de ±1 pitch por gesto, el overshoot
 * mostrado a d = 1 pitch es 0.5·(1 − 1/(COEF+1)) = 0.375 cuentas: alcanza el
 * GURU_TRIGGER (0.35) solo con un empuje firme (~78% del recorrido), y las
 * oscilaciones chicas no disparan. Tope visual < 0.5 cuentas siempre.
 */
export const RUBBER_COEF = 3;
/** Constante del snap: 90% del recorrido en ~190 ms. */
export const SNAP_RATE = 0.012;
/** Snap bajo prefers-reduced-motion: asentado (< epsilon) en ~100 ms. */
export const SNAP_RATE_REDUCED = 0.04;
/** Distancia a la que el snap se considera asentado (px). */
export const SNAP_EPSILON_PX = 0.5;
/** Sesgo del target de snap al salir del momentum (fracción de pitch). */
export const SNAP_TRAVEL_BIAS = 0.2;
/** Swipe hacia abajo = avanzar. Un solo lugar para invertirlo (modo zurdo futuro). */
export const DIRECTION_SIGN: 1 | -1 = 1;

export interface StrandPhysicsOptions {
    pitch: number; // px por cuenta
    mode: MalaMode;
    bounds: PositionBounds | null; // en unidades de cuenta (del motor)
    reducedMotion?: boolean;
    /** Única frontera px → cuentas: se llama en cada cambio de posición. */
    onChange?: (positionBeads: number) => void;
}

interface VelocitySample {
    y: number;
    t: number;
}

export class StrandPhysics {
    private pitch: number;
    private mode: MalaMode;
    private bounds: PositionBounds | null;
    private reducedMotion: boolean;
    private onChange?: (positionBeads: number) => void;

    private state: PhysicsState = 'idle';
    private offset = 0; // px, crece al avanzar cuentas

    // dragging
    private dragStartY = 0;
    private dragStartT = 0;
    private dragStartOffset = 0;
    private dragStartRest = 0; // reposo (px) al iniciar el gesto
    private samples: VelocitySample[] = [];

    // momentum / snapping
    private velocity = 0; // px/ms
    private snapTarget = 0; // px
    private lastTickT = 0;

    constructor(opts: StrandPhysicsOptions) {
        this.pitch = opts.pitch;
        this.mode = opts.mode;
        this.bounds = opts.bounds;
        this.reducedMotion = opts.reducedMotion ?? false;
        this.onChange = opts.onChange;
    }

    getState(): PhysicsState {
        return this.state;
    }

    getPositionBeads(): number {
        return this.offset / this.pitch;
    }

    setMode(mode: MalaMode, bounds: PositionBounds | null): void {
        this.mode = mode;
        this.bounds = bounds;
        this.state = 'idle';
        this.velocity = 0;
    }

    setReducedMotion(value: boolean): void {
        this.reducedMotion = value;
    }

    /**
     * Teletransporta la hebra SIN pasar por posiciones intermedias.
     * Es el camino correcto tras resetear el motor (cambio de mantra):
     * animar desde la posición vieja alimentaría al motor recién puesto
     * en cero con esa posición, y la contaría entera como avance.
     */
    jumpTo(positionBeads: number): void {
        this.state = 'idle';
        this.velocity = 0;
        this.offset = this.constrain(positionBeads * this.pitch);
        this.onChange?.(this.offset / this.pitch);
    }

    /** En resize: re-escala el offset preservando la cuenta actual. */
    setPitch(pitch: number): void {
        const beads = this.offset / this.pitch;
        const targetBeads = this.snapTarget / this.pitch;
        this.pitch = pitch;
        this.offset = beads * pitch;
        this.snapTarget = targetBeads * pitch;
        this.notify();
    }

    pointerDown(y: number, t: number): void {
        // Agarrar la hebra en vuelo interrumpe momentum/snapping.
        this.state = 'dragging';
        this.velocity = 0;
        this.dragStartY = y;
        this.dragStartT = t;
        this.dragStartOffset = this.offset;
        this.dragStartRest = Math.round(this.offset / this.pitch) * this.pitch;
        this.samples = [{ y, t }];
    }

    pointerMove(y: number, t: number): void {
        if (this.state !== 'dragging') {
            return;
        }

        this.samples.push({ y, t });

        // Ring buffer acotado: solo interesa la ventana reciente.
        if (this.samples.length > 32) {
            this.samples.shift();
        }

        const raw =
            this.dragStartOffset + DIRECTION_SIGN * (y - this.dragStartY);
        this.offset = this.constrain(raw);
        this.notify();
    }

    /**
     * Devuelve 'tap' si el gesto fue un toque (el composable decide qué
     * hacer con él — avanzar en asistido, nada en tradicional).
     */
    pointerUp(y: number, t: number): 'tap' | 'drag' {
        if (this.state !== 'dragging') {
            return 'drag';
        }

        const isTap =
            Math.abs(y - this.dragStartY) < TAP_MAX_MOVEMENT_PX &&
            t - this.dragStartT < TAP_MAX_DURATION_MS;

        if (isTap) {
            // Sin movimiento real: volver al reposo (el composable puede
            // encadenar animateToBead tras engine.advance()).
            this.startSnap(this.nearestRest(this.offset), t);

            return 'tap';
        }

        const releaseVelocity = this.sampleVelocity(t);

        if (
            this.mode === 'assisted' &&
            !this.reducedMotion &&
            Math.abs(releaseVelocity) >= FLICK_MIN_VELOCITY
        ) {
            this.state = 'momentum';
            this.velocity = releaseVelocity;
            this.lastTickT = t;
        } else {
            // Tradicional (siempre) o suelta lenta: snap al reposo más
            // cercano — si el drag pasó el punto medio, es la cuenta vecina.
            this.startSnap(this.nearestRest(this.offset), t);
        }

        return 'drag';
    }

    /** Anima hacia una cuenta (unidades de cuenta). Interrumpible. */
    animateToBead(positionBeads: number, now: number): void {
        this.startSnap(positionBeads * this.pitch, now);
    }

    /**
     * Avanza la animación. Devuelve true mientras haya movimiento (el
     * composable puede pausar el rAF cuando devuelve false y no hay drag).
     */
    tick(now: number): boolean {
        if (this.state === 'momentum') {
            const dt = Math.min(now - this.lastTickT, 100); // clamp anti-tab-suspend
            this.lastTickT = now;

            if (dt > 0) {
                this.velocity *= Math.pow(FRICTION, dt / 16.67);
                this.offset = this.constrain(this.offset + this.velocity * dt);
                this.notify();
            }

            if (Math.abs(this.velocity) < MOMENTUM_MIN_VELOCITY) {
                // Target sesgado en la dirección de viaje.
                const bias = Math.sign(this.velocity) * SNAP_TRAVEL_BIAS;
                this.startSnap(
                    this.nearestRest(this.offset + bias * this.pitch),
                    now,
                );
            }

            return true;
        }

        if (this.state === 'snapping') {
            const dt = Math.min(now - this.lastTickT, 100);
            this.lastTickT = now;

            if (dt > 0) {
                const rate = this.reducedMotion ? SNAP_RATE_REDUCED : SNAP_RATE;
                this.offset +=
                    (this.snapTarget - this.offset) *
                    (1 - Math.exp(-dt * rate));

                if (Math.abs(this.snapTarget - this.offset) < SNAP_EPSILON_PX) {
                    this.offset = this.snapTarget;
                    this.state = 'idle';
                }

                this.notify();
            }

            return this.state !== 'idle';
        }

        return false;
    }

    // ── internos ────────────────────────────────────────────────────────────

    private notify(): void {
        this.onChange?.(this.offset / this.pitch);
    }

    /** Velocidad de suelta: Δy/Δt sobre la ventana reciente (px/ms). */
    private sampleVelocity(t: number): number {
        const windowStart = t - VELOCITY_WINDOW_MS;
        const recent = this.samples.filter((s) => s.t >= windowStart);

        if (recent.length < 2) {
            return 0;
        }

        const first = recent[0];
        const last = recent[recent.length - 1];
        const dt = last.t - first.t;

        if (dt <= 0) {
            return 0;
        }

        return (DIRECTION_SIGN * (last.y - first.y)) / dt;
    }

    private nearestRest(offset: number): number {
        let rest = Math.round(offset / this.pitch) * this.pitch;

        if (this.bounds !== null) {
            rest = Math.min(
                Math.max(rest, this.bounds.min * this.pitch),
                this.bounds.max * this.pitch,
            );
        }

        return rest;
    }

    private startSnap(target: number, now: number): void {
        this.snapTarget = target;
        this.state = 'snapping';
        this.velocity = 0;
        this.lastTickT = now;
    }

    /**
     * Restricciones del modo tradicional: máx. ±1 pitch por gesto y
     * rubber-band contra los límites del segmento. El asistido es libre.
     */
    private constrain(raw: number): number {
        if (this.mode !== 'traditional') {
            return raw;
        }

        // Máximo una cuenta por gesto (el ritmo del ritual).
        let constrained = Math.min(
            Math.max(raw, this.dragStartRest - this.pitch),
            this.dragStartRest + this.pitch,
        );

        if (this.bounds === null) {
            return constrained;
        }

        const min = this.bounds.min * this.pitch;
        const max = this.bounds.max * this.pitch;

        if (constrained > max) {
            constrained = max + this.rubberBand(constrained - max);
        } else if (constrained < min) {
            constrained = min - this.rubberBand(min - constrained);
        }

        return constrained;
    }

    /** Fórmula iOS: overshoot mostrado, asintótico a 0.5·pitch. */
    private rubberBand(excess: number): number {
        return (
            0.5 *
            this.pitch *
            (1 - 1 / ((excess * RUBBER_COEF) / this.pitch + 1))
        );
    }
}
