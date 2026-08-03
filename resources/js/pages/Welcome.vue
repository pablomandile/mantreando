<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login } from '@/routes';
import { register } from '@/routes';

/**
 * Landing pública mínima y serena. Las metas de SEO van acá (única página
 * indexable); el resto de la app vive detrás del login.
 */
</script>

<template>
    <Head title="Tu mala virtual">
        <meta
            name="description"
            content="malaflow es un mala virtual para tu práctica diaria de mantras: 108 cuentas, dos modos de práctica, funciona sin conexión y guarda tu progreso."
        />
    </Head>

    <div
        class="flex min-h-dvh flex-col items-center justify-center gap-10 bg-background p-6 text-foreground"
    >
        <main class="flex max-w-sm flex-col items-center gap-6 text-center">
            <img
                src="/icons/icon-192.png"
                alt=""
                class="size-20 rounded-3xl shadow-md"
            />

            <div class="space-y-2">
                <h1 class="text-3xl font-semibold tracking-tight">malaflow</h1>
                <p class="text-muted-foreground">
                    Tu mala de 108 cuentas, siempre con vos. Practicá tus
                    mantras, mantené tu compromiso diario y seguí tu progreso —
                    incluso sin conexión.
                </p>
            </div>

            <div class="flex w-full flex-col gap-2">
                <Link
                    v-if="$page.props.auth.user"
                    href="/practice"
                    class="rounded-md bg-foreground px-5 py-2.5 text-sm font-medium text-background"
                >
                    Continuar practicando
                </Link>
                <template v-else>
                    <Link
                        :href="register()"
                        class="rounded-md bg-foreground px-5 py-2.5 text-sm font-medium text-background"
                    >
                        Comenzar a practicar
                    </Link>
                    <Link
                        :href="login()"
                        class="rounded-md border px-5 py-2.5 text-sm"
                    >
                        Ya tengo cuenta
                    </Link>
                </template>
            </div>
        </main>

        <footer class="text-xs text-muted-foreground">
            Serenidad, sin distracciones. 108 cuentas, un solo camino.
        </footer>
    </div>
</template>
