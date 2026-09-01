/**
 * El ábaco del retiro de aproximación: tres líneas de diez cuentas.
 *
 * La primera cuenta por vuelta de mala, la segunda por decena de vueltas y la
 * tercera por centena. NO se guarda la posición de las cuentas: las tres
 * líneas son las últimas tres cifras del conteo EN VUELTAS DE MALA, así que
 * el acarreo sale solo — pasar de 9 a 10 vueltas deja la primera línea en
 * cero y mueve una de la segunda, igual que en el contador de madera.
 *
 * Cada vuelta de mala son 108 cuentas reales, no 100: el mala físico (y el
 * digital) tiene 108 cuentas, y lo que se muestra en el número grande es la
 * cifra real recitada, no una vuelta redondeada a 100. Mil vueltas —las tres
 * líneas dando la vuelta completa una vez— son exactamente 108.000, la meta
 * tradicional del retiro.
 *
 * El número, en cambio, nunca se vacía: sigue creciendo hacia la cifra del
 * retiro.
 *
 * TypeScript puro, sin Vue ni Inertia, como lib/mala: así se testea solo.
 */

/** Cuentas por línea. */
export const BEADS_PER_ROW = 10;

/** Cuentas reales por vuelta de mala: el mala físico tiene 108, no 100. */
export const RECITATIONS_PER_MALA = 108;

/** Lo que suma —en cuentas reales— correr una cuenta de cada línea. */
export const ROW_VALUES = [1, 10, 100].map(
    (malas) => malas * RECITATIONS_PER_MALA,
) as [number, number, number];

export type RowIndex = 0 | 1 | 2;

/**
 * Cuántas cuentas están corridas a la derecha en cada línea, de arriba abajo
 * (unidades, decenas, centenas de vueltas de mala). Siempre 0..9.
 *
 * El conteo viaja en cuentas reales; acá se lo pasa primero a vueltas de
 * mala (dividiendo por 108) y recién ahí se sacan los dígitos, exactamente
 * como antes de que las líneas valieran 108/1080/10800 en vez de 1/10/100.
 */
export function digitsOf(count: number): [number, number, number] {
    const malas = Math.max(0, Math.floor(count / RECITATIONS_PER_MALA));

    return [
        malas % 10,
        Math.floor(malas / 10) % 10,
        Math.floor(malas / 100) % 10,
    ];
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
