import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue } from 'laravel-vue-i18n';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeInstallPrompt } from '@/lib/install';
import { initializePwa } from '@/lib/pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                // Claves en español (idioma fuente): si falta la traducción,
                // la clave misma es el texto. Solo en.json aporta strings.
                // Carga EAGER: síncrona antes del primer render, así trans()
                // (estático) ya ve los mensajes activos — el locale solo
                // cambia con recarga completa de página.
                lang: document.documentElement.lang || 'es',
                // CRÍTICO: el fallback default es 'en' — con es.json vacío,
                // toda clave faltante en es caería al inglés. Con 'es', la
                // clave (español fuente) es el texto final.
                fallbackLang: 'es',
                resolve: (lang: string) => {
                    // Solo se carga en.json: en español las claves YA son el
                    // texto final. lang/es.json existe pero es de backend
                    // (páginas de error y mails de Laravel) y no debe entrar
                    // al bundle.
                    if (lang !== 'en') {
                        return {};
                    }

                    const langs = import.meta.glob('../../lang/en.json', {
                        eager: true,
                    }) as Record<string, { default: Record<string, string> }>;

                    return langs['../../lang/en.json']?.default ?? {};
                },
            })
            // el viene tipado como nullable, pero Inertia solo llama a setup
            // con el elemento raíz ya resuelto: si faltara, no habría app.
            .mount(el as Element);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            // Spike de debug: fullscreen sin chrome de layout.
            case name === 'practice/Spike':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// PWA: service worker + Background Sync de la outbox...
initializePwa();

// Captura de `beforeinstallprompt`: tiene que quedar registrado ANTES de que
// el navegador lo dispare, si no el botón de instalar nunca aparece.
initializeInstallPrompt();
