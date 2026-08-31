import { describe, expect, it } from 'vitest';
import { applyMove, digitsOf } from './abacus';

describe('digitsOf', () => {
    it('reparte el conteo en unidades, decenas y centenas', () => {
        expect(digitsOf(0)).toEqual([0, 0, 0]);
        expect(digitsOf(7)).toEqual([7, 0, 0]);
        expect(digitsOf(35)).toEqual([5, 3, 0]);
        expect(digitsOf(735)).toEqual([5, 3, 7]);
    });

    it('vuelve las cuentas a la izquierda en cada vuelta, sin tocar el número', () => {
        // Al pasar la décima unidad, la primera línea se vacía y avanza la segunda.
        expect(digitsOf(9)).toEqual([9, 0, 0]);
        expect(digitsOf(10)).toEqual([0, 1, 0]);

        // Lo mismo cuando se completan las diez decenas.
        expect(digitsOf(99)).toEqual([9, 9, 0]);
        expect(digitsOf(100)).toEqual([0, 0, 1]);

        // Y cuando se completan las diez centenas: las tres líneas quedan a
        // la izquierda, pero el conteo sigue en 1000.
        expect(digitsOf(999)).toEqual([9, 9, 9]);
        expect(digitsOf(1000)).toEqual([0, 0, 0]);
    });

    it('sigue funcionando muy por encima de las mil', () => {
        expect(digitsOf(12480)).toEqual([0, 8, 4]);
        expect(digitsOf(100000)).toEqual([0, 0, 0]);
    });

    it('tolera basura', () => {
        expect(digitsOf(-5)).toEqual([0, 0, 0]);
        expect(digitsOf(12.7)).toEqual([2, 1, 0]);
    });
});

describe('applyMove', () => {
    it('suma lo que vale cada línea', () => {
        expect(applyMove(0, 0, 1)).toBe(1);
        expect(applyMove(0, 1, 1)).toBe(10);
        expect(applyMove(0, 2, 1)).toBe(100);
    });

    it('devolver una cuenta resta', () => {
        expect(applyMove(5, 0, -1)).toBe(4);
        expect(applyMove(35, 1, -1)).toBe(25);
        expect(applyMove(735, 2, -1)).toBe(635);
    });

    it('nunca baja de cero', () => {
        expect(applyMove(0, 0, -1)).toBe(0);
        expect(applyMove(5, 1, -1)).toBe(0);
        expect(applyMove(50, 2, -1)).toBe(0);
    });

    it('acarrea al correr la décima cuenta', () => {
        expect(digitsOf(applyMove(9, 0, 1))).toEqual([0, 1, 0]);
        expect(digitsOf(applyMove(99, 0, 1))).toEqual([0, 0, 1]);
        expect(digitsOf(applyMove(999, 0, 1))).toEqual([0, 0, 0]);
    });
});
