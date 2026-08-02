import { router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

interface AuthUser {
    timezone: string | null;
}

let captured = false;

/**
 * Captura la timezone del dispositivo una sola vez cuando el usuario
 * autenticado no tiene una (cuentas creadas vía Google). El registro por
 * email ya la manda como campo oculto del formulario.
 */
export function useDeviceTimezone(): void {
    const page = usePage<{ auth: { user: AuthUser | null } }>();

    onMounted(() => {
        if (captured) return;

        const user = page.props.auth?.user;
        if (!user || user.timezone !== null) return;

        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!timezone) return;

        captured = true;
        router.patch(
            '/settings/timezone',
            { timezone },
            { preserveState: true, preserveScroll: true },
        );
    });
}
