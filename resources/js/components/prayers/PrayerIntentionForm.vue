<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans as t } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface ReasonOption {
    id: number;
    name: string;
    color: string | null;
}

interface IntentionData {
    id?: number;
    name: string;
    custom_reason: string | null;
    reason_ids: number[];
}

const props = defineProps<{
    /** El catálogo que mantiene el administrador. */
    reasons: ReasonOption[];
    intention?: IntentionData;
}>();

const form = useForm({
    name: props.intention?.name ?? '',
    reason_ids: [...(props.intention?.reason_ids ?? [])],
    custom_reason: props.intention?.custom_reason ?? '',
});

// "Otro" no es una fila del catálogo: es el texto libre. Arranca marcado solo
// si la persona ya venía con uno escrito.
const other = ref(Boolean(props.intention?.custom_reason));

function toggleReason(id: number, checked: boolean): void {
    form.reason_ids = checked
        ? [...form.reason_ids, id]
        : form.reason_ids.filter((current) => current !== id);
}

function toggleOther(checked: boolean): void {
    other.value = checked;

    // Al desmarcar se limpia: si no, el motivo viejo se guardaría igual.
    if (!checked) {
        form.custom_reason = '';
    }
}

function submit(): void {
    if (props.intention?.id) {
        form.put(`/prayers/${props.intention.id}`);
    } else {
        form.post('/prayers');
    }
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">{{ t('Nombre') }} *</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                autocomplete="off"
                :placeholder="t('Como la tengas presente')"
            />
            <InputError :message="form.errors.name" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">
                {{ t('Motivos') }} *
            </legend>

            <div class="flex flex-col gap-1">
                <Label
                    v-for="reason in reasons"
                    :key="reason.id"
                    class="flex items-center gap-3 rounded-md px-2 py-2 font-normal transition-colors hover:bg-accent/50"
                >
                    <Checkbox
                        :model-value="form.reason_ids.includes(reason.id)"
                        @update:model-value="
                            (value: boolean | 'indeterminate') =>
                                toggleReason(reason.id, value === true)
                        "
                    />
                    <span
                        class="prayer-chip rounded-full border px-2.5 py-0.5 text-sm"
                        :data-color="reason.color ?? undefined"
                    >
                        {{ reason.name }}
                    </span>
                </Label>

                <Label
                    class="flex items-center gap-3 rounded-md px-2 py-2 font-normal transition-colors hover:bg-accent/50"
                >
                    <Checkbox
                        :model-value="other"
                        @update:model-value="
                            (value: boolean | 'indeterminate') =>
                                toggleOther(value === true)
                        "
                    />
                    <span class="text-sm">{{ t('Otro') }}</span>
                </Label>
            </div>

            <div v-if="other" class="mt-1 grid gap-2 pl-9">
                <Input
                    v-model="form.custom_reason"
                    :aria-label="t('Escribí el motivo')"
                    :placeholder="t('Escribí el motivo')"
                    maxlength="255"
                />
                <InputError :message="form.errors.custom_reason" />
            </div>

            <InputError :message="form.errors.reason_ids" />
        </fieldset>

        <div class="flex items-center gap-3">
            <Button :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ intention?.id ? t('Guardar cambios') : t('Agregar') }}
            </Button>
            <slot name="actions" />
        </div>
    </form>
</template>
