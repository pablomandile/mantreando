/**
 * REGLA DE ORO (§7 del plan): la fecha local de una sesión se calcula SIEMPRE
 * en el dispositivo, en el momento de la práctica, y viaja en el payload.
 * El servidor jamás la deriva ni recalcula fechas históricas: un cambio de
 * timezone por viaje no reescribe rachas pasadas.
 */

/**
 * Fecha 'YYYY-MM-DD' en la timezone dada (o la del dispositivo).
 * en-CA formatea nativamente como YYYY-MM-DD.
 */
export function getLocalDate(
    timeZone?: string,
    date: Date = new Date(),
): string {
    try {
        return new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(date);
    } catch {
        // Timezone inválida guardada: caer a la del dispositivo.
        return new Intl.DateTimeFormat('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(date);
    }
}

/** UUID v4 para sesiones, generado en el cliente (idempotencia del sync). */
export function newSessionUuid(): string {
    return crypto.randomUUID();
}
