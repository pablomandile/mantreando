<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Menu } from '@lucide/vue'
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
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
    <!-- Hamburguesa y no el loto de la marca: acá gana lo que la gente ya
         reconoce como "abrir el menú". Cerrada a plena tinta; abierta, apenas
         atenuada como única seña. -->
    <Menu
      class="size-5 transition-opacity"
      :class="isMobile || state === 'collapsed' ? '' : 'opacity-60'"
    />
    <span class="sr-only">Mostrar u ocultar el panel lateral</span>
  </Button>
</template>
