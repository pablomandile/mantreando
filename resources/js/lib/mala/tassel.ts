/**
 * Colores de borla que se ofrecen en Mi mala.
 *
 * Los hex viven SOLO acá: los usan la borla del mala y su muestra en los
 * ajustes. El backend guarda nada más que la clave (MalaPreset::TASSEL_COLORS),
 * así que un color se retoca en un único lugar.
 *
 * `null` (sin elegir) no está en la tabla a propósito: significa "que siga al
 * material de las cuentas", y eso lo resuelve el CSS de la borla con su
 * fallback a --bead-lo.
 */
export const TASSEL_COLORS = {
    saffron: '#e0a13c',
    crimson: '#b3332e',
    jade: '#7ba05b',
    indigo: '#3b5fa8',
    rose: '#d4738f',
    ivory: '#e2d3b0',
} as const;

export type TasselColor = keyof typeof TASSEL_COLORS;

/** Hex de una clave guardada; null/desconocida → null (hereda el material). */
export function tasselHex(color: string | null | undefined): string | null {
    if (color === null || color === undefined) {
        return null;
    }

    return TASSEL_COLORS[color as TasselColor] ?? null;
}
