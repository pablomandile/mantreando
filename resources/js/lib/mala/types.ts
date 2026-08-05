/**
 * Tipos y constantes del mala virtual.
 *
 * Modelo central: un mala físico es un LOOP de 109 slots — 108 cuentas más
 * la cuenta gurú. La posición es un eje continuo en "unidades de cuenta"
 * (float); los enteros son puntos de reposo; slot(p) = mod(round(p), 109),
 * y el slot 108 es el gurú.
 *
 * Este módulo es TypeScript puro: cero imports de Vue/Inertia (isla offline).
 */

export const BEAD_COUNT = 108;
export const SLOT_COUNT = 109; // 108 cuentas + gurú: un loop físico
export const GURU_SLOT = 108;
export const VISIBLE_BEADS = 12;
export const POOL_SIZE = 16; // 12 visibles + 2 arriba + 2 abajo (reciclados)

/**
 * Overshoot (en cuentas) que hay que empujar contra el gurú en modo
 * tradicional para que cuente la recitación 108 y se invierta la dirección.
 * Debe ser menor que el tope visual del rubber-band (~0.5).
 */
export const GURU_TRIGGER = 0.35;

/** Lock del avance discreto (tap) para evitar dobles toques. */
export const ADVANCE_LOCK_MS = 150;

export type MalaMode = 'traditional' | 'assisted';
export type Direction = 1 | -1;
export type BeadMaterial = 'wood' | 'bodhi' | 'red' | 'blue'; // el spike usa 'wood'

export type MalaEvent =
    | { type: 'bead'; count: number; totalCount: number; direction: Direction }
    | { type: 'guru' } // gurú tocado (tradicional) o pasado (asistido)
    | { type: 'completed'; round: number; totalCount: number } // 108 contadas
    | { type: 'reset' };

export interface MalaSnapshot {
    mode: MalaMode;
    count: number; // 0..108 dentro de la vuelta actual
    round: number; // vueltas completadas
    totalCount: number;
    direction: Direction; // siempre 1: el mala cuenta en un solo sentido
    position: number; // continua, en unidades de cuenta
    restSlot: number; // 0..108, slot de reposo más cercano
}

export interface PositionBounds {
    min: number;
    max: number;
}

/** mod que devuelve siempre 0..n-1 (el % de JS es negativo para p < 0). */
export function mod(value: number, n: number): number {
    return ((value % n) + n) % n;
}

/** Slot 0..108 correspondiente a una posición continua. */
export function slotAt(position: number): number {
    return mod(Math.round(position), SLOT_COUNT);
}
