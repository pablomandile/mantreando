import { requestBackgroundSync } from '@/lib/pwa';
import { fetchBootstrap, postSessions } from './api';
import { db } from './db';
import type { OutboxItem } from './types';

/**
 * Sincronizador de la isla.
 *
 * - refreshBootstrap(): baja mantras + usuario y los cachea (pisa la tabla).
 * - drainOutbox(): sube la cola en lotes de ≤50; borra los aceptados
 *   ('created'/'duplicate' — ambos significan "el server ya la tiene") y
 *   marca los 'invalid' para no reintentarlos.
 *
 * Un mutex simple evita drenados concurrentes (evento online + apertura de
 * app pueden disparar a la vez). Background Sync llega en la etapa PWA;
 * el fallback de sincronizar al abrir la app queda siempre.
 */

const BATCH_SIZE = 50;

let draining: Promise<void> | null = null;

export async function refreshBootstrap(): Promise<void> {
    const payload = await fetchBootstrap();

    await db.transaction('rw', db.mantras, db.meta, async () => {
        await db.mantras.clear();
        await db.mantras.bulkPut(payload.mantras);
        await db.meta.bulkPut([
            { key: 'user', value: payload.user },
            { key: 'today', value: payload.today },
            { key: 'totals', value: payload.totals },
            { key: 'streak', value: payload.streak },
            { key: 'malaPreset', value: payload.mala_preset },
            { key: 'lastBootstrapAt', value: Date.now() },
        ]);
    });
}

export function drainOutbox(): Promise<void> {
    // Mutex: si ya hay un drenado en curso, reusar esa promesa.
    draining ??= doDrain().finally(() => {
        draining = null;
    });

    return draining;
}

async function doDrain(): Promise<void> {
    for (;;) {
        const batch = (await db.outbox
            .orderBy('createdAt')
            .filter((item) => !item.invalid)
            .limit(BATCH_SIZE)
            .toArray()) as OutboxItem[];

        if (batch.length === 0) {
            return;
        }

        // Pick explícito: garantiza la forma exacta del payload del API.
        const result = await postSessions(
            batch.map((item) => ({
                uuid: item.uuid,
                mantra_id: item.mantra_id,
                mode: item.mode,
                recitations: item.recitations,
                completed_malas: item.completed_malas,
                started_at: item.started_at,
                ended_at: item.ended_at,
                duration_seconds: item.duration_seconds,
                local_date: item.local_date,
            })),
        );

        const accepted: string[] = [];
        const invalid: string[] = [];

        for (const item of result.results) {
            if (item.uuid === null) {
                continue;
            }

            if (item.status === 'invalid') {
                invalid.push(item.uuid);
            } else {
                accepted.push(item.uuid);
            }
        }

        await db.transaction('rw', db.outbox, async () => {
            if (accepted.length > 0) {
                await db.outbox.bulkDelete(accepted);
            }

            for (const uuid of invalid) {
                await db.outbox.update(uuid, { invalid: true });
            }
        });

        // Si el server no aceptó nada nuevo, cortar para no loopear.
        if (accepted.length === 0) {
            return;
        }
    }
}

/** Sincronización completa: bootstrap + outbox. Silenciosa si no hay red. */
export async function syncAll(): Promise<void> {
    if (!navigator.onLine) {
        return;
    }

    try {
        await drainOutbox();
        await refreshBootstrap();
    } catch {
        // Sin red o server caído: la isla sigue con su cache local y la
        // outbox se reintenta en el próximo trigger (online / focus / carga).
    }
}

/** Encola una sesión terminada. El sync corre después, cuando haya red. */
export async function enqueueSession(
    session: Omit<OutboxItem, 'createdAt' | 'invalid'>,
): Promise<void> {
    await db.outbox.put({ ...session, createdAt: Date.now() });

    // Best effort: si el navegador soporta Background Sync, el SW avisará
    // al recuperar conectividad aunque esta pestaña se cierre.
    void requestBackgroundSync();
}
