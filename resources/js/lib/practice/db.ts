import Dexie from 'dexie';
import type { EntityTable } from 'dexie';
import type {
    ActiveSessionState,
    CachedMantra,
    MetaEntry,
    OutboxItem,
} from './types';

/**
 * Base local de la isla de práctica.
 *
 * - mantras: cache del bootstrap (se pisa entero en cada refresh).
 * - outbox: sesiones completadas/abandonadas pendientes de subir.
 * - sessionState: fila única 'active' con la práctica en curso.
 * - meta: lastBootstrapAt, userId, etc.
 */
class MalaflowDB extends Dexie {
    mantras!: EntityTable<CachedMantra, 'id'>;
    outbox!: EntityTable<OutboxItem, 'uuid'>;
    sessionState!: EntityTable<ActiveSessionState, 'key'>;
    meta!: EntityTable<MetaEntry, 'key'>;

    constructor() {
        super('malaflow');

        this.version(1).stores({
            mantras: 'id, name', // name indexado: la lista ordena por nombre
            outbox: 'uuid, createdAt',
            sessionState: 'key',
            meta: 'key',
        });
    }
}

export const db = new MalaflowDB();
