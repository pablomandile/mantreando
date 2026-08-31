<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, X } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface Deity {
    id: number;
    name: string;
    image_url: string | null;
    syllable_image_url: string | null;
    color: string | null;
    position: number;
    stages: number;
    /** Alguien está haciendo este retiro, así que no se borra. */
    in_use: boolean;
}

const props = defineProps<{
    deities: Deity[];
    nextPosition: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Retiro de aproximación', href: '/retreats' },
            { title: 'Deidades', href: '/retreats/deities' },
        ],
    },
});

// El intento de borrar una deidad en uso vuelve por withErrors, no por el
// formulario: se muestra arriba de todo.
const page = usePage();
const globalErrors = computed<string[]>(() => {
    const message = (page.props.errors as Record<string, string>)?.deity;

    return message ? [message] : [];
});

const form = useForm({ name: '', position: props.nextPosition });

function create(): void {
    // Va a la pantalla de edición: una deidad sin mantras no sirve de nada.
    form.post('/retreats/deities');
}

function destroy(deity: Deity): void {
    if (
        confirm(
            t(
                '¿Eliminar esta deidad? Deja de estar disponible para todas las cuentas.',
            ),
        )
    ) {
        router.delete(`/retreats/deities/${deity.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="t('Deidades del retiro')" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <Link
                href="/retreats"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ t('Retiro de aproximación') }}
            </Link>
            <h1 class="text-xl font-semibold">
                {{ t('Deidades del retiro') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('Quedan disponibles para todas las cuentas.') }}
            </p>
        </div>

        <AlertError v-if="globalErrors.length" :errors="globalErrors" />

        <form class="grid gap-4 rounded-xl border p-4" @submit.prevent="create">
            <div class="grid gap-2">
                <Label for="new-deity">{{ t('Deidad nueva') }} *</Label>
                <Input
                    id="new-deity"
                    v-model="form.name"
                    required
                    maxlength="120"
                    autocomplete="off"
                    :placeholder="t('Por ejemplo: Migtsema')"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('Después le cargás las imágenes y los mantras.') }}
                </p>
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <Button :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    <Plus v-else class="size-4" />
                    {{ t('Crear deidad') }}
                </Button>
            </div>
        </form>

        <p
            v-if="deities.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('Todavía no hay deidades cargadas.') }}
        </p>

        <div v-else class="flex flex-col gap-2">
            <div
                v-for="deity in deities"
                :key="deity.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        v-if="deity.image_url"
                        :src="deity.image_url"
                        :alt="deity.name"
                        class="size-12 shrink-0 rounded-lg object-cover"
                    />
                    <span
                        v-else
                        class="flex size-12 shrink-0 items-center justify-center rounded-lg border border-dashed text-[10px] text-muted-foreground"
                    >
                        {{ t('Sin imagen') }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ deity.name }}</p>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            #{{ deity.position }} ·
                            {{
                                deity.stages === 0
                                    ? t('sin mantras')
                                    : t(':count mantras', {
                                          count: String(deity.stages),
                                      })
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <Link
                        :href="`/retreats/deities/${deity.id}/edit`"
                        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="t('Editar')"
                        :title="t('Editar')"
                    >
                        <Pencil class="size-4" />
                    </Link>
                    <button
                        type="button"
                        :disabled="deity.in_use"
                        class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-40"
                        :aria-label="t('Eliminar')"
                        :title="
                            deity.in_use
                                ? t('Alguien está haciendo este retiro.')
                                : t('Eliminar')
                        "
                        @click="destroy(deity)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
