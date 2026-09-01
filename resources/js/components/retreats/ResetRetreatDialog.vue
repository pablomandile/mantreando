<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RotateCcw } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Reiniciar un retiro es serio: vacía meses de conteo. Doble confirmación,
 * como pidió el usuario — un sí/no primero y, recién si confirma, escribir
 * el nombre de la deidad para pasar a la segunda. El servidor vuelve a
 * comprobar el nombre: esto no es solo un candado de UI.
 */
const props = defineProps<{
    retreatId: number;
    deityName: string;
}>();

// El POST no lleva preserveState: las props (stages, current_stage_id) ya
// llegan frescas en 0 cuando esto se emite. Lo que NO se resincroniza solo
// es el estado local de la pantalla (el ref del conteo en curso, el
// debounce pendiente, la copia en localStorage): eso lo limpia quien
// escuche este evento.
const emit = defineEmits<{ reset: [] }>();

const open = ref(false);
const step = ref<'ask' | 'confirm'>('ask');
const typed = ref('');
const processing = ref(false);
const error = ref<string | null>(null);

const matches = computed(
    () => typed.value.trim().toLowerCase() === props.deityName.toLowerCase(),
);

// Reabrir siempre arranca desde cero, sin arrastrar el paso o el texto de
// un intento anterior (cancelado o fallido).
watch(open, (value) => {
    if (!value) {
        step.value = 'ask';
        typed.value = '';
        error.value = null;
    }
});

function confirmFirstStep(): void {
    step.value = 'confirm';
}

function submit(): void {
    if (!matches.value || processing.value) {
        return;
    }

    processing.value = true;
    error.value = null;

    router.post(
        `/retreats/${props.retreatId}/reset`,
        { confirm_name: typed.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                emit('reset');
            },
            onError: (errors) => {
                error.value = errors.confirm_name ?? null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <Button
            type="button"
            size="sm"
            variant="outline"
            class="text-destructive hover:text-destructive"
            @click="open = true"
        >
            <RotateCcw class="size-4" />
            {{ t('Reiniciar conteo') }}
        </Button>

        <DialogContent>
            <template v-if="step === 'ask'">
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{ t('¿Reiniciar el conteo de este retiro?') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'Todas las etapas vuelven a cero y quedan abiertas de nuevo. No se puede deshacer.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ t('No') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="confirmFirstStep"
                    >
                        {{ t('Sí') }}
                    </Button>
                </DialogFooter>
            </template>

            <template v-else>
                <DialogHeader class="space-y-3">
                    <DialogTitle>
                        {{ t('Escribí el nombre de la deidad para confirmar') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'Escribí ":name" tal cual, sin espacios de más.',
                                {
                                    name: props.deityName,
                                },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="confirm-deity-name" class="sr-only">
                        {{ t('Nombre de la deidad') }}
                    </Label>
                    <Input
                        id="confirm-deity-name"
                        v-model="typed"
                        autocomplete="off"
                        autofocus
                        :placeholder="props.deityName"
                        @keydown.enter="submit"
                    />
                    <InputError :message="error ?? undefined" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ t('Cancelar') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="!matches || processing"
                        @click="submit"
                    >
                        {{ t('Reiniciar conteo') }}
                    </Button>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
