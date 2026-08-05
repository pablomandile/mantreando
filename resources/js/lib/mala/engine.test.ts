import { describe, expect, it } from 'vitest';
import { MalaEngine } from './engine';
import type { MalaEvent } from './types';
import { BEAD_COUNT, GURU_SLOT, mod, SLOT_COUNT } from './types';

/** PRNG determinista (mulberry32) para fuzz reproducible. */
function mulberry32(seed: number): () => number {
    let a = seed;

    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function collectEvents(engine: MalaEngine): MalaEvent[] {
    const events: MalaEvent[] = [];
    engine.subscribe((e) => events.push(e));

    return events;
}

/** Barrido suave de posición entre dos puntos (simula frames de la física). */
function sweep(engine: MalaEngine, from: number, to: number, step = 0.2): void {
    const direction = Math.sign(to - from) || 1;

    for (let p = from; direction > 0 ? p < to : p > to; p += direction * step) {
        engine.setPosition(p);
    }

    engine.setPosition(to);
}

describe('MalaEngine — modo tradicional', () => {
    it('cuenta exactamente 108 por vuelta durante 3 vueltas, siempre bajando', () => {
        const engine = new MalaEngine({ mode: 'traditional' });
        const events = collectEvents(engine);

        // Tres vueltas idénticas: 0 → 107 (107 llegadas) + empuje al gurú
        // (la 108) + rewind al arranque. La dirección nunca se invierte.
        for (let round = 1; round <= 3; round++) {
            sweep(engine, 0, 107);
            expect(engine.getSnapshot().count).toBe(107);
            expect(engine.getSnapshot().direction).toBe(1);

            engine.setPosition(107.4);
            expect(engine.getSnapshot().round).toBe(round);
            expect(engine.getSnapshot().count).toBe(0);
            expect(engine.getSnapshot().direction).toBe(1);

            // Lo que hace useMala junto con physics.jumpTo(0).
            engine.rewindToStart();
            expect(engine.getSnapshot().position).toBe(0);
        }

        expect(engine.getSnapshot().totalCount).toBe(3 * BEAD_COUNT);

        // Cada vuelta emitió: 107 beads + 1 guru + 1 bead(108) + completed
        expect(events.filter((e) => e.type === 'completed')).toHaveLength(3);
        expect(events.filter((e) => e.type === 'guru')).toHaveLength(3);
        expect(events.filter((e) => e.type === 'bead')).toHaveLength(
            3 * BEAD_COUNT,
        );
    });

    it('tras el rewind la vuelta siguiente cuenta desde la primera cuenta', () => {
        const engine = new MalaEngine({ mode: 'traditional' });

        sweep(engine, 0, 107);
        engine.setPosition(107.4); // 108 + completed
        engine.rewindToStart();

        // El extremum volvió a 0 con la posición: sin eso, bajar de nuevo no
        // contaría nada (el máximo histórico seguiría en 107).
        sweep(engine, 0, 3);
        expect(engine.getSnapshot().count).toBe(3);
        expect(engine.getSnapshot().totalCount).toBe(BEAD_COUNT + 3);
    });

    it('el rewind no toca la contabilidad acumulada', () => {
        const engine = new MalaEngine({ mode: 'traditional' });

        sweep(engine, 0, 107);
        engine.setPosition(107.4);
        engine.rewindToStart();

        const snapshot = engine.getSnapshot();
        expect(snapshot.round).toBe(1);
        expect(snapshot.totalCount).toBe(BEAD_COUNT);
        expect(snapshot.count).toBe(0);
    });

    it('la posición nunca necesita cruzar el gurú (segmento acotado)', () => {
        const engine = new MalaEngine({ mode: 'traditional' });
        const bounds = engine.bounds();

        expect(bounds).toEqual({ min: 0, max: 107 });
    });

    it('el rebote del gurú no re-dispara aunque la posición oscile (latch)', () => {
        const engine = new MalaEngine({ mode: 'traditional' });

        sweep(engine, 0, 107);
        engine.setPosition(107.4); // dispara
        expect(engine.getSnapshot().round).toBe(1);

        // Oscilación dentro de la zona del gurú durante el rebote
        engine.setPosition(107.38);
        engine.setPosition(107.42);
        engine.setPosition(107.3);
        engine.setPosition(107.45);
        expect(engine.getSnapshot().round).toBe(1); // sin doble disparo
    });

    it('sin rewind, asentar y volver a empujar el gurú suma cuentas espurias', () => {
        // Este es el motivo por el que useMala rebobina la hebra al recibir
        // 'completed': el latch solo cubre el rebote, no un empuje nuevo, así
        // que quedarse en el gurú deja sumar recitaciones sin recitar.
        const engine = new MalaEngine({ mode: 'traditional' });

        sweep(engine, 0, 107);
        engine.setPosition(107.4);
        expect(engine.getSnapshot().totalCount).toBe(BEAD_COUNT);

        engine.setPosition(107); // asienta: libera el latch
        engine.setPosition(107.4); // empuje nuevo, sin haber avanzado nada

        expect(engine.getSnapshot().totalCount).toBe(BEAD_COUNT + 1);
        expect(engine.getSnapshot().count).toBe(1);
    });

    it('retroceder dentro del segmento no descuenta', () => {
        const engine = new MalaEngine({ mode: 'traditional' });

        sweep(engine, 0, 5); // 5 cuentas
        expect(engine.getSnapshot().count).toBe(5);

        sweep(engine, 5, 2); // retroceso (dirección de conteo sigue +1)
        expect(engine.getSnapshot().count).toBe(5);

        sweep(engine, 2, 6); // re-avanza: la cuenta 6 recién al cruzar 5.5
        expect(engine.getSnapshot().count).toBe(6);
    });

    it('ignora advance() (el tap no es parte del ritual tradicional)', () => {
        const engine = new MalaEngine({ mode: 'traditional' });

        expect(engine.advance(1000)).toBe(false);
        expect(engine.getSnapshot().count).toBe(0);
    });
});

describe('MalaEngine — modo asistido', () => {
    it('cuenta por high-water mark y el gurú pasa sin contar', () => {
        const engine = new MalaEngine({ mode: 'assisted' });
        const events = collectEvents(engine);

        // Avanzar 109 slots = una vuelta física completa del loop
        sweep(engine, 0, SLOT_COUNT, 0.25);

        const gurus = events.filter((e) => e.type === 'guru');
        const completed = events.filter((e) => e.type === 'completed');
        expect(gurus).toHaveLength(1); // slot 108 pasó una vez
        expect(completed).toHaveLength(1); // 108 cuentas → vuelta completa
        expect(engine.getSnapshot().round).toBe(1);
        expect(engine.getSnapshot().totalCount).toBe(BEAD_COUNT);
    });

    it('scrub ida-y-vuelta sobre el mismo punto medio cuenta exactamente 1', () => {
        const engine = new MalaEngine({ mode: 'assisted' });

        // Cruza 0.5 → cuenta 1; vuelve; re-cruza varias veces
        engine.setPosition(0.6);
        engine.setPosition(0.4);
        engine.setPosition(0.7);
        engine.setPosition(0.2);
        engine.setPosition(0.9);
        expect(engine.getSnapshot().totalCount).toBe(1);
    });

    it('retroceder nunca descuenta (hebra infinita)', () => {
        const engine = new MalaEngine({ mode: 'assisted' });

        sweep(engine, 0, 20, 0.25);
        expect(engine.getSnapshot().totalCount).toBe(20);

        sweep(engine, 20, -30, 0.25); // scrub muy atrás
        expect(engine.getSnapshot().totalCount).toBe(20);

        sweep(engine, -30, 20, 0.25); // volver hasta el máximo previo
        expect(engine.getSnapshot().totalCount).toBe(20); // nada nuevo

        sweep(engine, 20, 21, 0.25); // superar el máximo → cuenta
        expect(engine.getSnapshot().totalCount).toBe(21);
    });

    it('fuzz: 10k pasos aleatorios — count == cruces hacia adelante distintos', () => {
        const random = mulberry32(108108);
        const engine = new MalaEngine({ mode: 'assisted' });

        let position = 0;
        let maxPosition = 0;

        for (let i = 0; i < 10_000; i++) {
            position += (random() - 0.45) * 3; // sesgo leve hacia adelante
            engine.setPosition(position);
            maxPosition = Math.max(maxPosition, position);
        }

        // Esperado: k en [1, floor(max+0.5)] cuyo slot no sea el gurú
        const lastBead = Math.floor(maxPosition + 0.5);
        let expected = 0;

        for (let k = 1; k <= lastBead; k++) {
            if (mod(k, SLOT_COUNT) !== GURU_SLOT) {
                expected++;
            }
        }

        expect(engine.getSnapshot().totalCount).toBe(expected);
    });

    it('advance() respeta el lock de 150 ms', () => {
        const engine = new MalaEngine({ mode: 'assisted' });

        expect(engine.advance(1000)).toBe(true);
        expect(engine.advance(1100)).toBe(false); // dentro del lock
        expect(engine.advance(1200)).toBe(true);
        expect(engine.getSnapshot().totalCount).toBe(2);
    });

    it('advance() sobre el slot gurú emite guru sin contar', () => {
        const engine = new MalaEngine({ mode: 'assisted' });
        const events = collectEvents(engine);

        sweep(engine, 0, 107, 0.25); // 107 cuentas, reposo en slot 107
        expect(engine.getSnapshot().totalCount).toBe(107);

        engine.advance(10_000); // hacia el slot 108 = gurú
        expect(engine.getSnapshot().totalCount).toBe(107);
        expect(events.filter((e) => e.type === 'guru')).toHaveLength(1);

        engine.advance(20_000); // slot 0 de la vuelta siguiente → cuenta 108
        expect(engine.getSnapshot().totalCount).toBe(BEAD_COUNT);
        expect(events.filter((e) => e.type === 'completed')).toHaveLength(1);
    });
});

describe('MalaEngine — común', () => {
    it('reset limpia el estado y emite el evento', () => {
        const engine = new MalaEngine({ mode: 'assisted' });
        const events = collectEvents(engine);

        sweep(engine, 0, 10, 0.25);
        engine.reset();

        const snapshot = engine.getSnapshot();
        expect(snapshot.count).toBe(0);
        expect(snapshot.totalCount).toBe(0);
        expect(snapshot.position).toBe(0);
        expect(events.at(-1)).toEqual({ type: 'reset' });
    });

    it('setMode reinicia la contabilidad', () => {
        const engine = new MalaEngine({ mode: 'assisted' });

        sweep(engine, 0, 10, 0.25);
        engine.setMode('traditional');

        expect(engine.getSnapshot().count).toBe(0);
        expect(engine.getSnapshot().mode).toBe('traditional');
        expect(engine.bounds()).toEqual({ min: 0, max: 107 });
    });

    it('unsubscribe deja de recibir eventos', () => {
        const engine = new MalaEngine({ mode: 'assisted' });
        const events: MalaEvent[] = [];
        const unsubscribe = engine.subscribe((e) => events.push(e));

        engine.setPosition(1);
        unsubscribe();
        engine.setPosition(2);

        expect(events).toHaveLength(1);
    });
});
