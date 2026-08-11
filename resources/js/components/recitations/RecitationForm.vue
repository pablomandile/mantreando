<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * Alta y edición de una recitación. Solo la ve un administrador: son las mismas
 * para todas las cuentas.
 */

interface RecitationData {
    id?: number;
    title: string;
    text: string;
    position: number;
}

const props = defineProps<{
    recitation?: RecitationData;
    /** Orden sugerido para una nueva: la deja al final de la lista. */
    nextPosition?: number;
}>();

const form = useForm({
    title: props.recitation?.title ?? '',
    text: props.recitation?.text ?? '',
    position: props.recitation?.position ?? props.nextPosition ?? 0,
});

function submit(): void {
    if (props.recitation?.id) {
        form.put(`/recitations/${props.recitation.id}`);
    } else {
        form.post('/recitations');
    }
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="title">{{ t('Título') }} *</Label>
            <Input id="title" v-model="form.title" required />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="text">{{ t('Texto') }} *</Label>
            <!-- min-h alto y font-mono: son textos largos, en versos, y la
                 lista los muestra respetando los saltos de línea. -->
            <textarea
                id="text"
                v-model="form.text"
                required
                class="flex min-h-72 w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            />
            <p class="text-xs text-muted-foreground">
                {{
                    t('Los saltos de línea se respetan tal como los escribís.')
                }}
            </p>
            <InputError :message="form.errors.text" />
        </div>

        <div class="grid gap-2">
            <Label for="position">{{ t('Orden') }}</Label>
            <Input
                id="position"
                v-model="form.position"
                type="number"
                min="0"
                class="max-w-24"
            />
            <p class="text-xs text-muted-foreground">
                {{
                    t('Menor primero. Con el mismo número, ordena por título.')
                }}
            </p>
            <InputError :message="form.errors.position" />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{
                    recitation?.id
                        ? t('Guardar cambios')
                        : t('Crear recitación')
                }}
            </Button>
            <slot name="actions" />
        </div>
    </form>
</template>
