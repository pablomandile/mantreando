import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue } from 'laravel-vue-i18n';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

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
                resolve: (lang: string) => {
                    const langs = import.meta.glob('../../lang/*.json', {
                        eager: true,
                    }) as Record<string, { default: Record<string, string> }>;

                    return langs[`../../lang/${lang}.json`]?.default ?? {};
                },
            })
            .mount(el);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            // Pantallas de práctica inmersivas: sin chrome de layout.
            case name === 'practice/Spike':
            case name === 'practice/Session':
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
