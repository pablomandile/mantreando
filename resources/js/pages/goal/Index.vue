<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Panel "Objetivo": la meta diaria (la que sigue el switch del mala) y la
 * meta global acumulada (opcional).
 */

const props = defineProps<{
    dailyGoal: number;
    totalGoal: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Objetivo', href: '/goal' }],
    },
});

// Cantidades tradicionales de la práctica con mala
const PRESETS = [7, 21, 27, 54, 108];

const form = useForm({
    daily_goal: props.dailyGoal,
    total_goal: props.totalGoal,
});

function submit(): void {
    form.patch('/goal', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Objetivo')" />

    <div class="mx-auto flex w-full max-w-md flex-1 flex-col gap-6 p-4">
        <header>
            <h1 class="text-xl font-semibold">{{ t('Objetivo') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{
                    t(
                        'Cuántas recitaciones querés contar por día de CADA mantra. Al alcanzarlas, la práctica te lo celebra. Si un mantra tiene su propio compromiso diario en su ficha, ese manda.',
                    )
                }}
            </p>
        </header>

        <form class="space-y-8" @submit.prevent="submit">
            <!-- Meta diaria -->
            <section class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in PRESETS"
                        :key="preset"
                        type="button"
                        class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                        :class="
                            form.daily_goal === preset
                                ? 'border-foreground bg-foreground text-background'
                                : 'text-muted-foreground hover:bg-accent'
                        "
                        @click="form.daily_goal = preset"
                    >
                        {{ preset }}
                    </button>
                </div>
                <div class="grid gap-2">
                    <Label for="daily_goal">
                        {{ t('Cantidad personalizada diaria') }}
                    </Label>
                    <Input
                        id="daily_goal"
                        v-model.number="form.daily_goal"
                        type="number"
                        min="1"
                        max="1000000"
                        class="max-w-40"
                    />
                    <InputError :message="form.errors.daily_goal" />
                </div>
            </section>

            <!-- Meta global -->
            <section class="space-y-3">
                <div>
                    <h2 class="text-sm font-medium">
                        {{ t('Objetivo global') }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{
                            t(
                                'Recitaciones acumuladas desde el comienzo, sumando todos tus mantras. Por ejemplo: 100.000. Dejalo vacío si no querés una meta global.',
                            )
                        }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="total_goal">
                        {{ t('Cantidad personalizada global') }}
                    </Label>
                    <Input
                        id="total_goal"
                        v-model.number="form.total_goal"
                        type="number"
                        min="1"
                        max="1000000000"
                        class="max-w-40"
                        :placeholder="t('Sin meta')"
                    />
                    <InputError :message="form.errors.total_goal" />
                </div>
            </section>

            <p class="text-xs text-muted-foreground">
                {{
                    t(
                        'En la práctica podés desactivarlo con el switch "Cuenta libre" cuando quieras recitar sin meta.',
                    )
                }}
            </p>

            <Button :disabled="form.processing">{{ t('Guardar') }}</Button>
        </form>
    </div>
</template>
