<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import RecitationForm from '@/components/recitations/RecitationForm.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    recitation: {
        id: number;
        title: string;
        text: string;
        position: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Otras recitaciones', href: '/recitations' },
            { title: 'Editar', href: '' },
        ],
    },
});

function destroy(): void {
    // Se borra para todas las cuentas, así que el aviso lo dice.
    if (
        confirm(
            t(
                '¿Eliminar esta recitación? Deja de estar disponible para todas las cuentas.',
            ),
        )
    ) {
        router.delete(`/recitations/${props.recitation.id}`);
    }
}
</script>

<template>
    <Head :title="`${t('Editar recitación')} — ${recitation.title}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-xl font-semibold">{{ t('Editar recitación') }}</h1>
            <p class="text-sm text-muted-foreground">{{ recitation.title }}</p>
        </div>

        <RecitationForm :recitation="recitation">
            <template #actions>
                <Button type="button" variant="destructive" @click="destroy">
                    {{ t('Eliminar') }}
                </Button>
            </template>
        </RecitationForm>
    </div>
</template>
