import type { BootstrapPayload, OutboxItem, SyncResult } from './types';

/**
 * Cliente fetch de la isla. Mismo origen: la auth es la cookie de sesión
 * (Sanctum statefulApi) y el CSRF viaja como X-XSRF-TOKEN leído de la cookie.
 */

const ENDPOINTS = {
    bootstrap: '/api/v1/practice/bootstrap',
    sessions: '/api/v1/practice-sessions',
} as const;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
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

export function postSessions(
    sessions: Omit<OutboxItem, 'createdAt' | 'invalid'>[],
): Promise<SyncResult> {
    return request<SyncResult>(ENDPOINTS.sessions, {
        method: 'POST',
        body: JSON.stringify({ sessions }),
    });
}
