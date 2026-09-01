import { describe, expect, it } from 'vitest';
import { applyMove, digitsOf, RECITATIONS_PER_MALA } from './abacus';

describe('digitsOf', () => {
    it('reparte el conteo, en vueltas de mala, en unidades, decenas y centenas', () => {
        expect(digitsOf(0)).toEqual([0, 0, 0]);
        expect(digitsOf(7 * RECITATIONS_PER_MALA)).toEqual([7, 0, 0]);
        expect(digitsOf(35 * RECITATIONS_PER_MALA)).toEqual([5, 3, 0]);
        expect(digitsOf(735 * RECITATIONS_PER_MALA)).toEqual([5, 3, 7]);
    });

    it('vuelve las cuentas a la izquierda en cada vuelta, sin tocar el número', () => {
        // Al pasar la décima vuelta de mala, la primera línea se vacía y
        // avanza la segunda.
        expect(digitsOf(9 * RECITATIONS_PER_MALA)).toEqual([9, 0, 0]);
        expect(digitsOf(10 * RECITATIONS_PER_MALA)).toEqual([0, 1, 0]);

        // Lo mismo cuando se completan las diez decenas.
        expect(digitsOf(99 * RECITATIONS_PER_MALA)).toEqual([9, 9, 0]);
        expect(digitsOf(100 * RECITATIONS_PER_MALA)).toEqual([0, 0, 1]);

        // Y cuando se completan las diez centenas —mil vueltas de mala—: las
        // tres líneas quedan a la izquierda, pero el conteo real sigue en
        // 108.000, la meta tradicional del retiro.
        expect(digitsOf(999 * RECITATIONS_PER_MALA)).toEqual([9, 9, 9]);
        expect(digitsOf(1000 * RECITATIONS_PER_MALA)).toEqual([0, 0, 0]);
    });

    it('sigue funcionando muy por encima de las mil vueltas', () => {
        expect(digitsOf(12480 * RECITATIONS_PER_MALA)).toEqual([0, 8, 4]);
        expect(digitsOf(100000 * RECITATIONS_PER_MALA)).toEqual([0, 0, 0]);
    });

    it('tolera basura', () => {
        expect(digitsOf(-5)).toEqual([0, 0, 0]);
        // No hace falta que sea múltiplo de 108: se trunca hacia abajo a la
        // vuelta de mala completa más cercana antes de sacar los dígitos.
        expect(digitsOf(1234.7)).toEqual([1, 1, 0]);
    });
});

describe('applyMove', () => {
    it('suma lo que vale cada línea, en cuentas reales (no en vueltas)', () => {
        expect(applyMove(0, 0, 1)).toBe(108);
        expect(applyMove(0, 1, 1)).toBe(1080);
        expect(applyMove(0, 2, 1)).toBe(10800);
    });

    it('devolver una cuenta resta', () => {
        expect(applyMove(5 * RECITATIONS_PER_MALA, 0, -1)).toBe(
            4 * RECITATIONS_PER_MALA,
        );
        expect(applyMove(35 * RECITATIONS_PER_MALA, 1, -1)).toBe(
            25 * RECITATIONS_PER_MALA,
        );
        expect(applyMove(735 * RECITATIONS_PER_MALA, 2, -1)).toBe(
            635 * RECITATIONS_PER_MALA,
        );
    });

    it('nunca baja de cero', () => {
        expect(applyMove(0, 0, -1)).toBe(0);
        expect(applyMove(5 * RECITATIONS_PER_MALA, 1, -1)).toBe(0);
        expect(applyMove(50 * RECITATIONS_PER_MALA, 2, -1)).toBe(0);
    });

    it('acarrea al correr la décima cuenta', () => {
        expect(digitsOf(applyMove(9 * RECITATIONS_PER_MALA, 0, 1))).toEqual([
            0, 1, 0,
        ]);
        expect(digitsOf(applyMove(99 * RECITATIONS_PER_MALA, 0, 1))).toEqual([
            0, 0, 1,
        ]);
        expect(digitsOf(applyMove(999 * RECITATIONS_PER_MALA, 0, 1))).toEqual([
            0, 0, 0,
        ]);
    });

    it('mil vueltas completas de la primera línea llegan exactamente a 108.000', () => {
        let count = 0;

        for (let i = 0; i < 1000; i++) {
            count = applyMove(count, 0, 1);
        }

        expect(count).toBe(108000);
        expect(digitsOf(count)).toEqual([0, 0, 0]);
    });
});
