<script setup lang="ts">
import { X } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Una miniatura que al tocarla se ve en pantalla completa. Sin Dialog de
 * shadcn: ese está pensado para una tarjeta centrada con borde y padding,
 * no para una imagen a pantalla completa sobre fondo oscuro.
 *
 * Sin root único (la miniatura y el overlay son dos nodos), así que el
 * `class` del que llama no cae solo: inheritAttrs:false + v-bind="$attrs"
 * en la miniatura lo dejan explícito.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    src: string;
    alt: string;
}>();

const open = ref(false);

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

// Bloquea el scroll de atrás mientras se ve la imagen, y escucha Escape acá
// y no con un @keydown en el overlay: ese div no tiene el foco del teclado.
watch(open, (value) => {
    if (value) {
        document.addEventListener('keydown', onKeydown);
        document.body.style.overflow = 'hidden';
    } else {
        document.removeEventListener('keydown', onKeydown);
        document.body.style.overflow = '';
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);

    if (open.value) {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <img
        v-bind="$attrs"
        :src="props.src"
        :alt="props.alt"
        role="button"
        tabindex="0"
        class="cursor-zoom-in"
        @click="open = true"
        @keydown.enter="open = true"
        @keydown.space.prevent="open = true"
    />

    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            @click.self="open = false"
        >
            <img
                :src="props.src"
                :alt="props.alt"
                class="max-h-full max-w-full rounded-lg object-contain"
            />
            <button
                type="button"
                class="absolute top-4 right-4 inline-flex size-10 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
                :aria-label="t('Cerrar')"
                @click="open = false"
            >
                <X class="size-5" />
            </button>
        </div>
    </Teleport>
</template>
