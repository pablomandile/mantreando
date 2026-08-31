/**
 * El ábaco del retiro de aproximación: tres líneas de diez cuentas.
 *
 * La primera cuenta por mantra, la segunda por decena y la tercera por
 * centena. NO se guarda la posición de las cuentas: las tres líneas son las
 * últimas tres cifras del conteo, así que el acarreo sale solo — pasar de 9 a
 * 10 unidades deja la primera línea en cero y mueve una de la segunda, igual
 * que en el contador de madera.
 *
 * El número, en cambio, nunca se vacía: sigue creciendo hacia la cifra del
 * retiro.
 *
 * TypeScript puro, sin Vue ni Inertia, como lib/mala: así se testea solo.
 */

/** Cuentas por línea. */
export const BEADS_PER_ROW = 10;

/** Lo que suma correr una cuenta de cada línea. */
export const ROW_VALUES = [1, 10, 100] as const;

export type RowIndex = 0 | 1 | 2;

/**
 * Cuántas cuentas están corridas a la derecha en cada línea, de arriba abajo
 * (unidades, decenas, centenas). Siempre 0..9.
 */
export function digitsOf(count: number): [number, number, number] {
    const safe = Math.max(0, Math.floor(count));

    return [safe % 10, Math.floor(safe / 10) % 10, Math.floor(safe / 100) % 10];
}

/**
 * El conteo después de correr una cuenta. `direction` es 1 al empujar hacia
 * la derecha y -1 al devolverla, que es cómo se deshace.
 *
 * Nunca baja de cero: devolver una cuenta con el contador en cero no hace
 * nada, en vez de dejarlo en negativo.
 */
export function applyMove(
    count: number,
    row: RowIndex,
    direction: 1 | -1,
): number {
    const next = count + ROW_VALUES[row] * direction;

    return Math.max(0, next);
}
