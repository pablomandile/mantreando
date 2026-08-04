import { describe, expect, it } from 'vitest';
import type { StrandPhysicsOptions } from './physics';
import { FLICK_MIN_VELOCITY, StrandPhysics } from './physics';

const PITCH = 66; // px por cuenta (≈ 800/12 en un móvil típico)

function makePhysics(overrides: Partial<StrandPhysicsOptions> = {}): {
    physics: StrandPhysics;
    positions: number[];
} {
    const positions: number[] = [];
    const physics = new StrandPhysics({
        pitch: PITCH,
        mode: 'assisted',
        bounds: null,
        onChange: (p) => positions.push(p),
        ...overrides,
    });

    return { physics, positions };
}

/** Corre tick() con frames de dt fijo hasta que la física quede idle. */
function settle(physics: StrandPhysics, startT: number, dt = 16.67, maxFrames = 1000): number {
    let t = startT;

    for (let i = 0; i < maxFrames; i++) {
        t += dt;

        if (!physics.tick(t) && physics.getState() === 'idle') {
            return t;
        }
    }

    return t;
}

/** Drag sintético: baja el dedo, lo mueve en pasos y lo suelta. */
function drag(
    physics: StrandPhysics,
    opts: { fromY: number; toY: number; steps?: number; startT?: number; msPerStep?: number },
): { result: 'tap' | 'drag'; endT: number } {
    const { fromY, toY, steps = 10, startT = 0, msPerStep = 16 } = opts;
    physics.pointerDown(fromY, startT);

    let t = startT;

    for (let i = 1; i <= steps; i++) {
        t = startT + i * msPerStep;
        physics.pointerMove(fromY + ((toY - fromY) * i) / steps, t);
    }

    const result = physics.pointerUp(toY, t);

    return { result, endT: t };
}

describe('StrandPhysics — dragging', () => {
    it('la hebra sigue al dedo 1:1 durante el drag (asistido)', () => {
        const { physics } = makePhysics();

        physics.pointerDown(100, 0);
        physics.pointerMove(100 + PITCH, 16); // un pitch hacia abajo

        expect(physics.getPositionBeads()).toBeCloseTo(1, 5);
        expect(physics.getState()).toBe('dragging');
    });

    it('un drag lento suelta con snap al reposo más cercano', () => {
        const { physics } = makePhysics();

        // 0.6 cuentas en 600 ms: lento, sin flick
        const { endT } = drag(physics, {
            fromY: 0,
            toY: PITCH * 0.6,
            steps: 30,
            msPerStep: 20,
        });

        expect(physics.getState()).toBe('snapping');
        settle(physics, endT);
        expect(physics.getPositionBeads()).toBeCloseTo(1, 5); // pasó el punto medio
        expect(physics.getState()).toBe('idle');
    });

    it('un drag corto que no pasa el punto medio vuelve atrás', () => {
        const { physics } = makePhysics();

        const { endT } = drag(physics, {
            fromY: 0,
            toY: PITCH * 0.3,
            steps: 30,
            msPerStep: 20,
        });

        settle(physics, endT);
        expect(physics.getPositionBeads()).toBeCloseTo(0, 5);
    });

    it('pointerDown en pleno momentum agarra la hebra (interrumpe)', () => {
        const { physics } = makePhysics();

        // Flick: 300 px en 100 ms = 3 px/ms
        const { endT } = drag(physics, { fromY: 0, toY: 300, steps: 6, msPerStep: 16 });
        expect(physics.getState()).toBe('momentum');

        physics.tick(endT + 16);
        physics.pointerDown(500, endT + 32);
        expect(physics.getState()).toBe('dragging');

        const before = physics.getPositionBeads();
        physics.pointerMove(510, endT + 48);
        expect(physics.getPositionBeads()).toBeCloseTo(before + 10 / PITCH, 5);
    });
});

describe('StrandPhysics — momentum (asistido)', () => {
    it('un flick entra en momentum y recorre varias cuentas', () => {
        const { physics } = makePhysics();

        // ~1.5 px/ms: debería recorrer ≈ 500 px ≈ 7-8 cuentas en total
        const { endT } = drag(physics, { fromY: 0, toY: 150, steps: 6, msPerStep: 16 });
        expect(physics.getState()).toBe('momentum');

        settle(physics, endT);

        const finalBeads = physics.getPositionBeads();
        expect(finalBeads).toBeGreaterThan(4);
        expect(finalBeads).toBeLessThan(12);
        expect(Number.isInteger(finalBeads)).toBe(true); // asentó en una cuenta
    });

    it('la fricción es frame-rate-independiente (60 vs 120 fps)', () => {
        const { physics: physics60 } = makePhysics();
        const { physics: physics120 } = makePhysics();

        const gesture = { fromY: 0, toY: 150, steps: 6, msPerStep: 16 };
        const { endT: end60 } = drag(physics60, gesture);
        const { endT: end120 } = drag(physics120, gesture);

        settle(physics60, end60, 16.67);
        settle(physics120, end120, 8.33);

        // Misma cuenta final (la integración difiere < 1 punto medio)
        expect(physics60.getPositionBeads()).toBe(physics120.getPositionBeads());
    });

    it('bajo reduced motion no hay momentum: snap directo', () => {
        const { physics } = makePhysics({ reducedMotion: true });

        const { endT } = drag(physics, { fromY: 0, toY: 150, steps: 6, msPerStep: 16 });
        expect(physics.getState()).toBe('snapping');

        settle(physics, endT);
        expect(physics.getPositionBeads()).toBe(2); // 150/66 ≈ 2.27 → snap a 2
    });
});

describe('StrandPhysics — modo tradicional', () => {
    function traditional(overrides: Partial<StrandPhysicsOptions> = {}) {
        return makePhysics({
            mode: 'traditional',
            bounds: { min: 0, max: 107 },
            ...overrides,
        });
    }

    it('nunca entra en momentum: el flick más fuerte hace snap', () => {
        const { physics } = traditional();

        const { endT } = drag(physics, { fromY: 0, toY: 400, steps: 6, msPerStep: 16 });
        expect(physics.getState()).toBe('snapping');

        settle(physics, endT);
        expect(physics.getPositionBeads()).toBe(1); // clamp: máx. 1 cuenta por gesto
    });

    it('clamp de ±1 pitch por gesto también en drags larguísimos', () => {
        const { physics } = traditional();

        physics.pointerDown(0, 0);
        physics.pointerMove(PITCH * 5, 100); // intenta 5 cuentas

        expect(physics.getPositionBeads()).toBeCloseTo(1, 5);
    });

    it('el rubber-band comprime el empuje contra el gurú y supera el trigger', () => {
        const { physics, positions } = traditional();

        // Situarse en la cuenta 107 (límite superior)
        physics.animateToBead(107, 0);
        settle(physics, 0);
        positions.length = 0;

        // Empuje firme: un pitch entero contra el límite
        physics.pointerDown(0, 10_000);
        physics.pointerMove(PITCH, 10_100);

        const overshoot = physics.getPositionBeads() - 107;
        expect(overshoot).toBeGreaterThan(0.35); // alcanza GURU_TRIGGER
        expect(overshoot).toBeLessThan(0.5); // tope visual del rubber-band

        // Al soltar, rebota y asienta de nuevo en 107
        physics.pointerUp(PITCH, 10_116);
        settle(physics, 10_116);
        expect(physics.getPositionBeads()).toBe(107);
    });

    it('un empuje tímido contra el gurú NO alcanza el trigger', () => {
        const { physics } = traditional();

        physics.animateToBead(107, 0);
        settle(physics, 0);

        physics.pointerDown(0, 10_000);
        physics.pointerMove(PITCH * 0.4, 10_100); // empuje suave

        expect(physics.getPositionBeads() - 107).toBeLessThan(0.35);
    });

    it('rubber-band espejado en el límite inferior (gurú del otro extremo)', () => {
        const { physics } = traditional();

        physics.pointerDown(0, 0);
        physics.pointerMove(-PITCH, 100); // empuje por debajo de 0

        const undershoot = -physics.getPositionBeads();
        expect(undershoot).toBeGreaterThan(0.35);
        expect(undershoot).toBeLessThan(0.5);
    });
});

describe('StrandPhysics — tap y utilitarios', () => {
    it('detecta tap: poco movimiento y corta duración', () => {
        const { physics } = makePhysics();

        physics.pointerDown(100, 0);
        physics.pointerMove(103, 50);
        expect(physics.pointerUp(103, 80)).toBe('tap');
    });

    it('movimiento largo o lento no es tap', () => {
        const { physics } = makePhysics();

        physics.pointerDown(100, 0);
        physics.pointerMove(120, 50);
        expect(physics.pointerUp(120, 80)).toBe('drag');

        physics.pointerDown(100, 1000);
        physics.pointerMove(102, 1400);
        expect(physics.pointerUp(102, 1400)).toBe('drag'); // 400 ms > umbral
    });

    it('jumpTo teletransporta sin posiciones intermedias (cambio de mantra)', () => {
        const { physics, positions } = makePhysics();

        // La hebra quedó en la cuenta 11 del mantra anterior
        physics.animateToBead(11, 0);
        settle(physics, 0);
        positions.length = 0;

        physics.jumpTo(0);

        // UNA sola notificación y directa a 0: un motor recién reseteado
        // no debe recibir la posición vieja (la contaría como avance).
        expect(positions).toEqual([0]);
        expect(physics.getPositionBeads()).toBe(0);
        expect(physics.getState()).toBe('idle');
    });

    it('jumpTo también corta un momentum en vuelo', () => {
        const { physics, positions } = makePhysics();

        drag(physics, { fromY: 500, toY: 620, steps: 4, msPerStep: 12 });
        expect(physics.getState()).toBe('momentum');
        positions.length = 0;

        physics.jumpTo(0);

        expect(positions).toEqual([0]);
        expect(physics.getState()).toBe('idle');
        // Sin velocidad residual: el siguiente tick no mueve nada
        expect(physics.tick(5000)).toBe(false);
        expect(physics.getPositionBeads()).toBe(0);
    });

    it('animateToBead llega exacto y termina idle', () => {
        const { physics } = makePhysics();

        physics.animateToBead(5, 0);
        settle(physics, 0);

        expect(physics.getPositionBeads()).toBe(5);
        expect(physics.getState()).toBe('idle');
    });

    it('setPitch re-escala preservando la cuenta actual', () => {
        const { physics } = makePhysics();

        physics.animateToBead(10, 0);
        settle(physics, 0);

        physics.setPitch(100); // rotación del dispositivo
        expect(physics.getPositionBeads()).toBeCloseTo(10, 5);
    });

    it('el snap bajo reduced motion asienta en menos de 100 ms', () => {
        const { physics } = makePhysics({ reducedMotion: true });

        physics.pointerDown(0, 0);
        physics.pointerMove(PITCH * 0.6, 300);
        physics.pointerUp(PITCH * 0.6, 300);

        let t = 300;
        let frames = 0;

        while (physics.tick((t += 16.67))) {
            frames++;
        }

        expect(frames * 16.67).toBeLessThanOrEqual(117); // ≤ 7 frames ≈ 100 ms
        expect(physics.getPositionBeads()).toBe(1);
    });

    it('la constante FLICK_MIN_VELOCITY define el umbral de momentum', () => {
        const { physics } = makePhysics();

        // Justo por debajo del umbral: 0.25 px/ms
        physics.pointerDown(0, 0);

        for (let i = 1; i <= 10; i++) {
            physics.pointerMove(i * 4, i * 16);
        }

        physics.pointerUp(40, 160);
        expect(physics.getState()).toBe('snapping');
        expect(0.25).toBeLessThan(FLICK_MIN_VELOCITY);
    });
});
