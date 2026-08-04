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
class MantreandoDB extends Dexie {
    mantras!: EntityTable<CachedMantra, 'id'>;
    outbox!: EntityTable<OutboxItem, 'uuid'>;
    sessionState!: EntityTable<ActiveSessionState, 'key'>;
    meta!: EntityTable<MetaEntry, 'key'>;

    constructor() {
        super('mantreando');

        this.version(1).stores({
            mantras: 'id, name', // name indexado: la lista ordena por nombre
            outbox: 'uuid, createdAt',
            sessionState: 'key',
            meta: 'key',
        });

        // v2: índice sort (orden personal de la biblioteca)
        this.version(2).stores({
            mantras: 'id, name, sort',
        });

        // v3: índice mantra_id en la outbox — el reinicio descarta lo
        // pendiente de UN mantra y where() exige que el campo esté indexado
        // (si no, SchemaError).
        this.version(3).stores({
            outbox: 'uuid, createdAt, mantra_id',
        });
    }
}

export const db = new MantreandoDB();
