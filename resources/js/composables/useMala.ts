import { onMounted, onUnmounted, reactive, ref, shallowRef } from 'vue';
import {
    isSoundEnabled,
    setSoundEnabled,
    soundGuru,
    soundTick,
    unlockAudio,
} from '@/lib/mala/audio';
import { MalaEngine } from '@/lib/mala/engine';
import {
    hapticGuru,
    hapticsSupported,
    hapticTick,
    isHapticsEnabled,
    setHapticsEnabled,
} from '@/lib/mala/haptics';
import { StrandPhysics } from '@/lib/mala/physics';
import type { MalaMode, MalaSnapshot } from '@/lib/mala/types';
import {
    GURU_SLOT,
    mod,
    POOL_SIZE,
    slotAt,
    VISIBLE_BEADS,
} from '@/lib/mala/types';

/**
 * Una cuenta del pool virtualizado. El pool tiene POOL_SIZE nodos DOM fijos
 * que se reciclan: cuando la ventana avanza, solo el nodo que sale recibe
 * un índice nuevo (k ≡ i mod POOL_SIZE) — nunca hay 108 nodos en el DOM.
 */
export interface PoolBead {
    id: number; // índice fijo del nodo (0..POOL_SIZE-1)
    k: number; // índice absoluto en el eje continuo
    slot: number; // mod(k, 109); 108 = gurú
    isGuru: boolean;
    top: number; // px, estático hasta el próximo reciclado
    active: boolean;
}

/** Cuántas cuentas del pool quedan por debajo de la zona activa. */
const POOL_BELOW = 6;

/**
 * Une motor + física + feedback a Vue. Es el ÚNICO dueño del loop de
 * requestAnimationFrame: cada frame avanza la física, escribe UNA sola
 * transform en el contenedor (compositor-only) y recicla el pool si la
 * ventana se movió. El motor recibe la posición vía onChange de la física
 * (la única frontera px → cuentas).
 */
export function useMala(initialMode: MalaMode = 'assisted') {
    const engine = new MalaEngine({ mode: initialMode });

    let pitch = 66;
    let activeY = 0;
    let containerEl: HTMLElement | null = null;
    let columnEl: HTMLElement | null = null;
    let surfaceEl: HTMLElement | null = null;

    const physics = new StrandPhysics({
        pitch,
        mode: initialMode,
        bounds: engine.bounds(),
        onChange: (positionBeads) => engine.setPosition(positionBeads),
    });

    const snapshot = shallowRef<MalaSnapshot>(engine.getSnapshot());
    const mode = ref<MalaMode>(initialMode);
    const haptics = ref(isHapticsEnabled());
    const sound = ref(isSoundEnabled());
    const fps = ref(0);
    const droppedFrames = ref(0);
    const pool = reactive<PoolBead[]>([]);

    // ── pool virtualizado ───────────────────────────────────────────────────

    let poolBase = Number.NaN;
    let activeK = Number.NaN;

    function beadTop(k: number): number {
        return activeY - k * pitch;
    }

    function refreshPool(position: number): void {
        const rest = Math.round(position);
        const base = rest - POOL_BELOW;

        if (
            base === poolBase &&
            rest === activeK &&
            pool.length === POOL_SIZE
        ) {
            return;
        }

        poolBase = base;
        activeK = rest;

        for (let i = 0; i < POOL_SIZE; i++) {
            // Asignación estable: k ≡ i (mod POOL_SIZE) → al avanzar una
            // cuenta solo un nodo cambia de contenido (salta POOL_SIZE).
            const k = base + mod(i - base, POOL_SIZE);
            const slot = slotAt(k);
            const bead: PoolBead = pool[i] ?? {
                id: i,
                k: Number.NaN,
                slot: 0,
                isGuru: false,
                top: 0,
                active: false,
            };

            if (pool[i] === undefined) {
                pool.push(bead);
            }

            if (bead.k !== k) {
                bead.k = k;
                bead.slot = slot;
                bead.isGuru = slot === GURU_SLOT;
                bead.top = beadTop(k);
            }

            const isActive = k === rest;

            if (bead.active !== isActive) {
                bead.active = isActive;
            }
        }
    }

    // ── loop rAF único ──────────────────────────────────────────────────────

    let rafId = 0;
    let running = false;
    let lastFrameT = 0;
    let frameCount = 0;
    let fpsWindowStart = 0;
    let lastSnapshotT = 0;

    function frame(now: number): void {
        physics.tick(now);

        const position = physics.getPositionBeads();

        if (containerEl !== null) {
            // ÚNICA escritura de estilo por frame: compositor-only.
            containerEl.style.transform = `translate3d(0, ${position * pitch}px, 0)`;
        }

        refreshPool(position);

        // Medidor de fps del HUD (ventana rodante de 1 s)
        frameCount++;

        if (lastFrameT > 0 && now - lastFrameT > 25) {
            droppedFrames.value++;
        }

        lastFrameT = now;

        if (now - fpsWindowStart >= 1000) {
            fps.value = Math.round(
                (frameCount * 1000) / (now - fpsWindowStart),
            );
            frameCount = 0;
            fpsWindowStart = now;
        }

        // El HUD no necesita el snapshot a 60 Hz: 10 Hz alcanza.
        if (now - lastSnapshotT > 100) {
            snapshot.value = engine.getSnapshot();
            lastSnapshotT = now;
        }

        rafId = requestAnimationFrame(frame);
    }

    function startLoop(): void {
        if (!running) {
            running = true;
            lastFrameT = 0;
            fpsWindowStart = performance.now();
            frameCount = 0;
            rafId = requestAnimationFrame(frame);
        }
    }

    function stopLoop(): void {
        running = false;
        cancelAnimationFrame(rafId);
    }

    function onVisibilityChange(): void {
        if (document.hidden) {
            stopLoop();
        } else {
            startLoop();
        }
    }

    // ── medidas y resize ────────────────────────────────────────────────────

    function measure(): void {
        // La hebra vive dentro de su superficie (que puede ser la página
        // completa o el área de contenido bajo el header del panel).
        const height =
            surfaceEl?.clientHeight ||
            window.visualViewport?.height ||
            window.innerHeight;
        pitch = height / VISIBLE_BEADS;
        activeY = height * 0.62; // arco natural del pulgar derecho

        physics.setPitch(pitch);

        if (columnEl !== null) {
            columnEl.style.setProperty('--pitch', `${pitch}px`);
        }

        poolBase = Number.NaN; // fuerza reciclado completo

        for (const bead of pool) {
            bead.top = beadTop(bead.k);
        }

        refreshPool(physics.getPositionBeads());
    }

    // ── punteros ────────────────────────────────────────────────────────────

    function onPointerDown(event: PointerEvent): void {
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
        unlockAudio(); // los navegadores exigen un gesto para el AudioContext
        physics.pointerDown(event.clientY, event.timeStamp);
    }

    function onPointerMove(event: PointerEvent): void {
        physics.pointerMove(event.clientY, event.timeStamp);
    }

    function onPointerUp(event: PointerEvent): void {
        const result = physics.pointerUp(event.clientY, event.timeStamp);

        if (
            result === 'tap' &&
            mode.value === 'assisted' &&
            engine.advance(event.timeStamp)
        ) {
            physics.animateToBead(
                engine.getSnapshot().position,
                event.timeStamp,
            );
        }
    }

    // ── API pública ─────────────────────────────────────────────────────────

    function setContainer(el: HTMLElement | null): void {
        containerEl = el;
    }

    function setColumn(el: HTMLElement | null): void {
        columnEl = el;

        if (el !== null) {
            el.style.setProperty('--pitch', `${pitch}px`);
        }
    }

    function setSurface(el: HTMLElement | null): void {
        surfaceEl = el;
        measure();
    }

    function setMode(next: MalaMode): void {
        mode.value = next;
        engine.setMode(next);
        physics.setMode(next, engine.bounds());
        // Salto duro, NO animación: el motor acaba de resetearse a cero y
        // el primer frame de una animación desde la posición vieja se lo
        // contaría entero como avance (el contador "arrastraba" el número
        // del mantra anterior al cambiar).
        physics.jumpTo(0);
        snapshot.value = engine.getSnapshot();
        poolBase = Number.NaN;
        refreshPool(0);
    }

    function reset(): void {
        engine.reset();
        physics.jumpTo(0);
        snapshot.value = engine.getSnapshot();
    }

    /**
     * Vuelta nueva: completadas las 108, la hebra vuelve al arranque para
     * seguir bajando (el mala no se invierte). Motor y física tienen que
     * saltar JUNTOS: si solo saltara la física, el frame siguiente
     * alimentaría al motor con la posición vieja y le contaría el tramo
     * entero de golpe; si solo saltara el motor, la física seguiría empujando
     * contra el gurú y volvería a disparar la 108.
     */
    function rewindToStart(): void {
        engine.rewindToStart();
        physics.jumpTo(0);
        snapshot.value = engine.getSnapshot();
        poolBase = Number.NaN;
        refreshPool(0);
    }

    /** Restaura una sesión interrumpida (recuperación de práctica). */
    function restore(state: {
        mode: MalaMode;
        count: number;
        round: number;
        totalCount: number;
        direction: 1 | -1;
        position: number;
    }): void {
        mode.value = state.mode;
        engine.restore(state);
        physics.setMode(state.mode, engine.bounds());
        physics.jumpTo(state.position);
        snapshot.value = engine.getSnapshot();
        poolBase = Number.NaN;
        refreshPool(state.position);
    }

    /** Suscripción directa a los eventos del motor (recorder de sesiones). */
    const subscribe = engine.subscribe.bind(engine);

    /** Estado inicial de feedback desde las preferencias del usuario. */
    function applyFeedbackPrefs(prefs: {
        haptics?: boolean;
        sound?: boolean;
    }): void {
        if (prefs.haptics !== undefined) {
            haptics.value = prefs.haptics;
            setHapticsEnabled(prefs.haptics);
        }

        if (prefs.sound !== undefined) {
            sound.value = prefs.sound;
            setSoundEnabled(prefs.sound);
        }
    }

    function toggleHaptics(): void {
        haptics.value = !haptics.value;
        setHapticsEnabled(haptics.value);
    }

    function toggleSound(): void {
        sound.value = !sound.value;
        setSoundEnabled(sound.value);
    }

    // ── ciclo de vida ───────────────────────────────────────────────────────

    let unsubscribe: (() => void) | null = null;
    let reducedMotionQuery: MediaQueryList | null = null;

    const onReducedMotionChange = () => {
        physics.setReducedMotion(reducedMotionQuery?.matches ?? false);
    };

    onMounted(() => {
        unsubscribe = engine.subscribe((event) => {
            if (event.type === 'bead') {
                hapticTick();
                soundTick();
            } else if (event.type === 'guru' || event.type === 'completed') {
                hapticGuru();
                soundGuru();
            }

            // Tradicional: la vuelta terminó contra el gurú, así que la hebra
            // arranca de nuevo desde 0 mientras se muestra la felicitación.
            // Es lo que saca la hebra de la zona del gurú: si se quedara ahí,
            // cada empuje siguiente contaría otra 108.
            // El asistido no lo necesita: su hebra es un loop sin límites.
            if (event.type === 'completed' && mode.value === 'traditional') {
                rewindToStart();
            }

            snapshot.value = engine.getSnapshot();
            lastSnapshotT = performance.now();
        });

        reducedMotionQuery = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        reducedMotionQuery.addEventListener('change', onReducedMotionChange);
        onReducedMotionChange();

        measure();
        window.visualViewport?.addEventListener('resize', measure);
        window.addEventListener('resize', measure);
        document.addEventListener('visibilitychange', onVisibilityChange);

        startLoop();
    });

    onUnmounted(() => {
        stopLoop();
        unsubscribe?.();
        reducedMotionQuery?.removeEventListener(
            'change',
            onReducedMotionChange,
        );
        window.visualViewport?.removeEventListener('resize', measure);
        window.removeEventListener('resize', measure);
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return {
        snapshot,
        pool,
        mode,
        haptics,
        sound,
        hapticsSupported,
        fps,
        droppedFrames,
        setContainer,
        setColumn,
        setSurface,
        setMode,
        reset,
        restore,
        subscribe,
        applyFeedbackPrefs,
        toggleHaptics,
        toggleSound,
        onPointerDown,
        onPointerMove,
        onPointerUp,
    };
}
