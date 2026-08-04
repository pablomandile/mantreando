<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import LotusIcon from '@/components/icons/LotusIcon.vue'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <!-- Loto en vez del ícono genérico de panel: es el botón de marca.
         Cerrado a plena tinta; abierto, apenas atenuado como única seña. -->
    <LotusIcon
      class="size-5 transition-opacity"
      :class="isMobile || state === 'collapsed' ? '' : 'opacity-60'"
    />
    <span class="sr-only">Mostrar u ocultar el panel lateral</span>
  </Button>
</template>
