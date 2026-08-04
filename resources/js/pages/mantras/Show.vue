<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Star } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    mantra: {
        id: number;
        name: string;
        original_name: string | null;
        transliteration: string | null;
        text: string;
        translation: string | null;
        description: string | null;
        benefits: string | null;
        image_url: string | null;
        is_system: boolean;
        category: string;
        can_edit: boolean;
    };
    prefs: {
        is_favorite: boolean;
        daily_commitment: number | null;
        total_goal: number | null;
    };
    progress: {
        total_recitations: number;
        streak_current: number;
        streak_max: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mantras', href: '/mantras' }],
    },
});

const settingsForm = useForm({
    daily_commitment: props.prefs.daily_commitment,
    total_goal: props.prefs.total_goal,
});

function saveSettings(): void {
    settingsForm.patch(`/mantras/${props.mantra.id}/practice-settings`, {
        preserveScroll: true,
    });
}

function toggleFavorite(): void {
    router.post(
        `/mantras/${props.mantra.id}/favorite`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="mantra.name" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <header class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ mantra.name }}</h1>
                <p
                    v-if="mantra.original_name"
                    class="mt-1 text-lg text-muted-foreground"
                >
                    {{ mantra.original_name }}
                </p>
                <span
                    class="mt-2 inline-block rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                >
                    {{ mantra.category }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-md p-2 hover:bg-accent"
                    :aria-label="
                        prefs.is_favorite
                            ? t('Quitar de favoritos')
                            : t('Agregar a favoritos')
                    "
                    @click="toggleFavorite"
                >
                    <Star
                        class="size-5"
                        :class="
                            prefs.is_favorite
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground'
                        "
                    />
                </button>
                <Button v-if="mantra.can_edit" as-child variant="ghost" size="sm">
                    <Link :href="`/mantras/${mantra.id}/edit`">
                        <Pencil class="size-4" />
                        {{ t('Editar') }}
                    </Link>
                </Button>
            </div>
        </header>

        <img
            v-if="mantra.image_url"
            :src="mantra.image_url"
            :alt="mantra.name"
            class="max-h-64 w-full rounded-xl object-cover"
        />

        <section class="rounded-xl border p-4">
            <p class="text-lg leading-relaxed">{{ mantra.text }}</p>
            <p
                v-if="mantra.transliteration"
                class="mt-2 text-sm text-muted-foreground italic"
            >
                {{ mantra.transliteration }}
            </p>
            <p
                v-if="mantra.translation"
                class="mt-2 text-sm text-muted-foreground"
            >
                {{ mantra.translation }}
            </p>
        </section>

        <section v-if="mantra.description" class="space-y-1">
            <h2 class="text-sm font-medium">{{ t('Descripción') }}</h2>
            <p class="text-sm whitespace-pre-line text-muted-foreground">
                {{ mantra.description }}
            </p>
        </section>

        <section v-if="mantra.benefits" class="space-y-1">
            <h2 class="text-sm font-medium">{{ t('Beneficios') }}</h2>
            <p class="text-sm whitespace-pre-line text-muted-foreground">
                {{ mantra.benefits }}
            </p>
        </section>

        <!-- Compromiso y objetivo (pivot del usuario, aplica también a mantras del sistema) -->
        <section class="rounded-xl border p-4">
            <h2 class="text-sm font-medium">
                {{ t('Mi práctica con este mantra') }}
            </h2>

            <div
                v-if="progress.total_recitations > 0"
                class="mt-3 space-y-2 text-sm text-muted-foreground"
            >
                <p>
                    <span class="font-medium text-foreground">
                        {{ progress.total_recitations.toLocaleString() }}
                    </span>
                    {{ t('recitaciones acumuladas') }}
                    <template v-if="progress.streak_current > 0">
                        ·
                        {{
                            t('racha de :count :unit', {
                                count: String(progress.streak_current),
                                unit:
                                    progress.streak_current === 1
                                        ? t('día')
                                        : t('días'),
                            })
                        }}
                    </template>
                </p>
                <div v-if="prefs.total_goal" class="space-y-1">
                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-foreground/70 transition-all"
                            :style="{
                                width: `${Math.min(100, (progress.total_recitations / prefs.total_goal) * 100)}%`,
                            }"
                        />
                    </div>
                    <p class="text-xs">
                        {{
                            t(':pct% de tu objetivo de :goal', {
                                pct: String(
                                    Math.min(
                                        100,
                                        Math.round(
                                            (progress.total_recitations /
                                                prefs.total_goal) *
                                                100,
                                        ),
                                    ),
                                ),
                                goal: prefs.total_goal.toLocaleString(),
                            })
                        }}
                    </p>
                </div>
            </div>
            <form
                class="mt-3 grid gap-4 sm:grid-cols-2"
                @submit.prevent="saveSettings"
            >
                <div class="grid gap-2">
                    <Label for="daily_commitment">
                        {{ t('Compromiso diario') }}
                    </Label>
                    <Input
                        id="daily_commitment"
                        v-model.number="settingsForm.daily_commitment"
                        type="number"
                        min="1"
                        :placeholder="t('Recitaciones por día')"
                    />
                    <InputError
                        :message="settingsForm.errors.daily_commitment"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="total_goal">{{ t('Objetivo total') }}</Label>
                    <Input
                        id="total_goal"
                        v-model.number="settingsForm.total_goal"
                        type="number"
                        min="1"
                        placeholder="100000"
                    />
                    <InputError :message="settingsForm.errors.total_goal" />
                </div>
                <div class="sm:col-span-2">
                    <Button size="sm" :disabled="settingsForm.processing">
                        {{ t('Guardar') }}
                    </Button>
                </div>
            </form>
        </section>

        <Button as-child variant="outline">
            <Link :href="`/practice?mantra=${mantra.id}`">
                {{ t('Practicar con este mantra') }}
            </Link>
        </Button>
    </div>
</template>
