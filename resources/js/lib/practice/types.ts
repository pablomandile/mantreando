/**
 * Tipos de la isla de práctica.
 *
 * REGLA: este módulo (lib/practice/**) es TypeScript puro sin imports de
 * Inertia ni de Vue: una vez cargada la página, la práctica funciona 100%
 * offline leyendo/escribiendo IndexedDB y hablando solo con /api/v1.
 */

export type PracticeMode = 'traditional' | 'assisted';

/** Mantra tal como lo devuelve GET /api/v1/practice/bootstrap y se cachea. */
export interface CachedMantra {
    id: number;
    is_system: boolean;
    name: string;
    original_name: string | null;
    transliteration: string | null;
    text: string;
    translation: string | null;
    description: string | null;
    benefits: string | null;
    image_url: string | null;
    category: {
        id: number;
        name: string;
        slug: string;
    } | null;
    pivot: {
        is_favorite: boolean;
        daily_commitment: number | null;
        total_goal: number | null;
    };
}

/** Usuario cacheado del bootstrap (lo que la isla necesita offline). */
export interface CachedUser {
    id: number;
    name: string;
    timezone: string | null;
    locale: string;
    theme: string;
    settings: Record<string, unknown>;
}

/**
 * Sesión pendiente de sincronizar (outbox). El uuid se genera acá, en el
 * cliente: el servidor hace insert-or-ignore por uuid, así los reintentos
 * son idempotentes por construcción.
 */
export interface OutboxItem {
    uuid: string;
    mantra_id: number;
    mode: PracticeMode;
    recitations: number;
    completed_malas: number;
    started_at: string; // ISO 8601
    ended_at: string; // ISO 8601
    duration_seconds: number;
    local_date: string; // 'YYYY-MM-DD' calculada EN el dispositivo
    createdAt: number; // epoch ms, para orden de drenado
    invalid?: boolean; // marcada por el server; no se reintenta
}

/**
 * Estado de la sesión en curso, persistido cada pocas cuentas para poder
 * recuperar una práctica interrumpida (fila única con key 'active').
 */
export interface ActiveSessionState {
    key: 'active';
    uuid: string;
    mantra_id: number;
    mode: PracticeMode;
    count: number;
    round: number;
    totalCount: number;
    direction: 1 | -1;
    position: number; // posición continua del mala (para restaurar exacto)
    started_at: string;
    updatedAt: number;
}

/** Metadatos varios de la isla (fila por clave). */
export interface MetaEntry {
    key: string;
    value: unknown;
}

/** Resultado por ítem del POST /api/v1/practice-sessions. */
export interface SyncItemResult {
    uuid: string | null;
    status: 'created' | 'duplicate' | 'invalid';
    errors?: Record<string, string[]>;
}

export interface SyncResult {
    results: SyncItemResult[];
    synced_at: string;
}

/** Progreso de hoy (para compromisos diarios), cacheado del bootstrap. */
export interface CachedToday {
    local_date: string;
    total: number;
    by_mantra: Record<string, number>;
}

export interface CachedMalaPreset {
    material: 'wood' | 'bodhi' | 'red' | 'blue';
    texture_url: string | null;
}

export interface BootstrapPayload {
    user: CachedUser;
    mantras: CachedMantra[];
    today: CachedToday;
    totals: { by_mantra: Record<string, number> };
    streak: { current: number; max: number };
    mala_preset: CachedMalaPreset;
    server_time: string;
}
