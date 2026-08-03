<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    practice: {
        haptics_enabled: boolean;
        sound_enabled: boolean;
        default_mode: 'traditional' | 'assisted';
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ajustes de práctica', href: '/settings/practice' },
        ],
    },
});

const form = useForm({
    haptics_enabled: props.practice.haptics_enabled,
    sound_enabled: props.practice.sound_enabled,
    default_mode: props.practice.default_mode,
});

function submit(): void {
    form.patch('/settings/practice', { preserveScroll: true });
}

const hapticsSupported =
    typeof navigator !== 'undefined' && 'vibrate' in navigator;
</script>

<template>
    <Head title="Ajustes de práctica" />

    <h1 class="sr-only">Ajustes de práctica</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Práctica"
            description="Cómo se comporta el mala durante la recitación"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-3">
                <Label>Modo por defecto</Label>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm"
                        :class="{
                            'bg-accent': form.default_mode === 'traditional',
                        }"
                        @click="form.default_mode = 'traditional'"
                    >
                        Tradicional
                        <span class="block text-xs text-muted-foreground">
                            Cuenta por cuenta, el gurú invierte la dirección
                        </span>
                    </button>
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm"
                        :class="{
                            'bg-accent': form.default_mode === 'assisted',
                        }"
                        @click="form.default_mode = 'assisted'"
                    >
                        Asistido
                        <span class="block text-xs text-muted-foreground">
                            Gestos libres con inercia, o tocar para avanzar
                        </span>
                    </button>
                </div>
                <InputError :message="form.errors.default_mode" />
            </div>

            <Label
                class="flex items-center gap-3"
                :class="{ 'opacity-60': !hapticsSupported }"
            >
                <Checkbox
                    :model-value="form.haptics_enabled"
                    :disabled="!hapticsSupported"
                    @update:model-value="
                        (value: boolean | 'indeterminate') =>
                            (form.haptics_enabled = value === true)
                    "
                />
                <span>
                    Vibración al pasar cada cuenta
                    <span
                        v-if="!hapticsSupported"
                        class="block text-xs text-muted-foreground"
                    >
                        No disponible en este dispositivo (p. ej. iPhone o
                        escritorio)
                    </span>
                </span>
            </Label>

            <Label class="flex items-center gap-3">
                <Checkbox
                    :model-value="form.sound_enabled"
                    @update:model-value="
                        (value: boolean | 'indeterminate') =>
                            (form.sound_enabled = value === true)
                    "
                />
                <span>Sonido suave de madera al pasar cada cuenta</span>
            </Label>

            <Button :disabled="form.processing">Guardar</Button>
        </form>
    </div>
</template>
