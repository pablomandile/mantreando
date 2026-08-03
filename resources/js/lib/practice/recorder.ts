import { db } from './db';
import { getLocalDate, newSessionUuid } from './localDate';
import type { ActiveSessionState, PracticeMode } from './types';

/**
 * Registrador de sesiones de práctica. TS puro (isla offline).
 *
 * Diseño de resiliencia:
 * - El estado de la sesión EN CURSO se persiste en Dexie (fila única
 *   'active') con throttle: si la app muere, la práctica se recupera.
 * - Al terminar (o descartar con cuentas hechas), la sesión va a la outbox
 *   con uuid de cliente y local_date del dispositivo; el sync la sube
 *   cuando haya red. Nunca se pierde una recitación.
 */

const PERSIST_THROTTLE_MS = 2000;

export class SessionRecorder {
    private state: ActiveSessionState | null = null;
    private lastPersistAt = 0;
    private persistPending = false;

    /** Sesión activa persistida (si existe y es del mantra pedido). */
    static async findActive(mantraId: number): Promise<ActiveSessionState | null> {
        const state = await db.sessionState.get('active');

        return state && state.mantra_id === mantraId ? state : null;
    }

    /** Cualquier sesión activa, sin filtrar por mantra. */
    static async findAnyActive(): Promise<ActiveSessionState | null> {
        return (await db.sessionState.get('active')) ?? null;
    }

    /** Arranca una sesión nueva o retoma una restaurada. */
    async begin(opts: {
        mantraId: number;
        mode: PracticeMode;
        resume?: ActiveSessionState | null;
    }): Promise<ActiveSessionState> {
        // Spread defensivo: si `resume` llega como proxy reactivo de Vue,
        // IndexedDB no puede clonarlo (DataCloneError).
        this.state = opts.resume
            ? { ...opts.resume }
            : {
                  key: 'active',
                  uuid: newSessionUuid(),
                  mantra_id: opts.mantraId,
                  mode: opts.mode,
                  count: 0,
                  round: 0,
                  totalCount: 0,
                  direction: 1,
                  position: 0,
                  started_at: new Date().toISOString(),
                  updatedAt: Date.now(),
              };

        await db.sessionState.put({ ...this.state });

        return this.state;
    }

    getState(): ActiveSessionState | null {
        return this.state;
    }

    /**
     * Actualización desde el snapshot del motor. Persistencia throttled
     * (y siempre al completar una vuelta: es el momento que más duele perder).
     */
    update(
        snapshot: {
            count: number;
            round: number;
            totalCount: number;
            direction: 1 | -1;
            position: number;
        },
        opts: { force?: boolean } = {},
    ): void {
        if (this.state === null) {
            return;
        }

        this.state.count = snapshot.count;
        this.state.round = snapshot.round;
        this.state.totalCount = snapshot.totalCount;
        this.state.direction = snapshot.direction;
        this.state.position = snapshot.position;
        this.state.updatedAt = Date.now();

        const now = Date.now();

        if (opts.force || now - this.lastPersistAt >= PERSIST_THROTTLE_MS) {
            this.lastPersistAt = now;
            this.persistPending = false;
            void db.sessionState.put({ ...this.state });
        } else {
            this.persistPending = true;
        }
    }

    /**
     * Cierra la sesión: si hubo recitaciones la encola en la outbox
     * (idempotente por uuid) y limpia el estado activo.
     * Devuelve true si se encoló algo.
     */
    async finish(timezone?: string): Promise<boolean> {
        const state = this.state;

        if (state === null) {
            return false;
        }

        this.state = null;
        await db.sessionState.delete('active');

        if (state.totalCount === 0) {
            return false; // sesión vacía: nada que registrar
        }

        const endedAt = new Date();
        const startedAt = new Date(state.started_at);
        const durationSeconds = Math.max(
            0,
            Math.round((endedAt.getTime() - startedAt.getTime()) / 1000),
        );

        await db.outbox.put({
            uuid: state.uuid,
            mantra_id: state.mantra_id,
            mode: state.mode,
            recitations: state.totalCount,
            completed_malas: state.round,
            started_at: state.started_at,
            ended_at: endedAt.toISOString(),
            duration_seconds: durationSeconds,
            // Regla de oro: la fecha local se calcula ACÁ, en el dispositivo.
            local_date: getLocalDate(timezone, endedAt),
            createdAt: Date.now(),
        });

        return true;
    }

    /** Descarta la sesión activa sin registrar nada. */
    async discard(): Promise<void> {
        this.state = null;
        await db.sessionState.delete('active');
    }

    /** Flush final para desmontes (si quedó un update sin persistir). */
    async flush(): Promise<void> {
        if (this.state !== null && this.persistPending) {
            await db.sessionState.put({ ...this.state });
            this.persistPending = false;
        }
    }
}
