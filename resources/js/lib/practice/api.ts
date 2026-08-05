import type { BootstrapPayload, OutboxItem, SyncResult } from './types';

/**
 * Cliente fetch de la isla. Mismo origen: la auth es la cookie de sesión
 * (Sanctum statefulApi) y el CSRF viaja como X-XSRF-TOKEN leído de la cookie.
 */

const ENDPOINTS = {
    bootstrap: '/api/v1/practice/bootstrap',
    sessions: '/api/v1/practice-sessions',
    today: '/api/v1/practice/today',
} as const;

/**
 * Sin esto un fetch puede quedar pendiente para siempre: en móvil pasa que la
 * conexión "existe" pero no transmite (y la PWA que vuelve de estar suspendida
 * es el caso típico), y entonces la promesa nunca resuelve ni rechaza. El
 * síntoma era el botón de reinicio clavado en "Reiniciando…", porque su
 * finally no llegaba a correr nunca.
 *
 * La isla es offline-first: es preferible fallar y quedarse con la cache local
 * antes que esperar a la red indefinidamente.
 */
const REQUEST_TIMEOUT_MS = 15_000;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        // Después de ...init: el corte por tiempo no es negociable para el
        // caller (hoy ninguno pasa su propio signal).
        signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...init.headers,
        },
    });

    if (!response.ok) {
        throw new ApiError(response.status, await response.text());
    }

    const json = (await response.json()) as { data: T };

    return json.data;
}

export class ApiError extends Error {
    constructor(
        public status: number,
        body: string,
    ) {
        super(`API ${status}: ${body.slice(0, 200)}`);
    }
}

export function fetchBootstrap(): Promise<BootstrapPayload> {
    return request<BootstrapPayload>(ENDPOINTS.bootstrap);
}

/**
 * Borra la práctica del día en el servidor. Sin esto el reinicio sería
 * cosmético: el siguiente bootstrap traería de vuelta el total.
 * Con mantraId borra solo ese mantra; sin él, el día entero.
 */
export function deleteToday(
    localDate: string,
    mantraId?: number,
): Promise<{ local_date: string }> {
    return request<{ local_date: string }>(ENDPOINTS.today, {
        method: 'DELETE',
        body: JSON.stringify({
            local_date: localDate,
            mantra_id: mantraId ?? null,
        }),
    });
}

export function postSessions(
    sessions: Omit<OutboxItem, 'createdAt' | 'invalid'>[],
): Promise<SyncResult> {
    return request<SyncResult>(ENDPOINTS.sessions, {
        method: 'POST',
        body: JSON.stringify({ sessions }),
    });
}
