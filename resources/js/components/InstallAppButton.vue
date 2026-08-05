<script setup lang="ts">
import { Download, Share, SquarePlus } from '@lucide/vue';
import { trans as t } from 'laravel-vue-i18n';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useInstallPrompt } from '@/lib/install';

/**
 * Ofrece instalar la app. Se esconde solo cuando no hay nada que ofrecer:
 * ya instalada, o navegador que no sabe instalar. En iOS abre instrucciones
 * porque Safari no expone un prompt programático.
 */
const { canInstall, needsManualSteps, promptInstall } = useInstallPrompt();

const showIosSteps = ref(false);

async function onClick(): Promise<void> {
    if (needsManualSteps.value) {
        showIosSteps.value = true;

        return;
    }

    await promptInstall();
}
</script>

<template>
    <SidebarMenu v-if="canInstall">
        <SidebarMenuItem>
            <SidebarMenuButton
                class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                :tooltip="t('Instalar app')"
                @click="onClick"
            >
                <Download />
                <span>{{ t('Instalar app') }}</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>

    <Dialog v-model:open="showIosSteps">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('Instalar mantreando') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'En iPhone y iPad la instalación se hace desde Safari, en dos pasos.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <ol class="space-y-4 text-sm">
                <li class="flex items-start gap-3">
                    <Share class="mt-0.5 size-5 shrink-0 text-primary" />
                    <span>
                        {{
                            t('Tocá el botón Compartir de la barra de Safari.')
                        }}
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <SquarePlus class="mt-0.5 size-5 shrink-0 text-primary" />
                    <span>
                        {{ t('Elegí «Añadir a pantalla de inicio».') }}
                    </span>
                </li>
            </ol>

            <p class="text-xs text-muted-foreground">
                {{
                    t(
                        'Desde el ícono de inicio la práctica funciona sin conexión, igual que en la web.',
                    )
                }}
            </p>
        </DialogContent>
    </Dialog>
</template>
