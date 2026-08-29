<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import PrayerIntentionForm from '@/components/prayers/PrayerIntentionForm.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    intention: {
        id: number;
        name: string;
        custom_reason: string | null;
        reason_ids: number[];
    };
    reasons: { id: number; name: string; color: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lista de oración', href: '/prayers' },
            { title: 'Editar', href: '' },
        ],
    },
});

function destroy(): void {
    // Lo normal es archivar, que conserva la fecha; esto es para altas
    // equivocadas, y el aviso lo dice.
    if (
        confirm(
            t(
                '¿Eliminar definitivamente? Si solo ya no hace falta orar, mejor archivar: así queda guardado con su fecha.',
            ),
        )
    ) {
        router.delete(`/prayers/${props.intention.id}`);
    }
}
</script>

<template>
    <Head :title="`${t('Editar')} — ${intention.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Editar') }}</h1>
            <p class="text-sm text-muted-foreground">{{ intention.name }}</p>
        </div>

        <PrayerIntentionForm :intention="intention" :reasons="reasons">
            <template #actions>
                <Button type="button" variant="destructive" @click="destroy">
                    {{ t('Eliminar') }}
                </Button>
            </template>
        </PrayerIntentionForm>
    </div>
</template>
