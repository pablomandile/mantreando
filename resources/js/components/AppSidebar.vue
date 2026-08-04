<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    ChartColumn,
    CircleDashed,
    Flower,
    Palette,
    Target,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

// En móvil el menú es un panel superpuesto: al elegir una opción hay que
// cerrarlo a mano. Se engancha a la navegación (y no al click de cada
// Link) para que valga también para el logo y el menú de usuario.
const { setOpenMobile } = useSidebar();
const stopListening = router.on('start', () => setOpenMobile(false));

onUnmounted(() => stopListening());

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Práctica'),
        href: '/practice',
        icon: Flower,
    },
    {
        title: trans('Mantras'),
        href: '/mantras',
        icon: BookOpen,
    },
    {
        title: trans('Objetivo'),
        href: '/goal',
        icon: Target,
    },
    {
        title: trans('Estadísticas'),
        href: '/stats',
        icon: ChartColumn,
    },
]);

// Atajos a los dos ajustes que se tocan seguido; siguen estando dentro de
// Ajustes, esto solo evita entrar ahí para cambiar el mala o el tema.
const customizationNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Mi mala'),
        href: '/settings/mala',
        icon: CircleDashed,
    },
    {
        title: trans('Apariencia'),
        href: '/settings/appearance',
        icon: Palette,
    },
]);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/practice">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                class="mt-4"
                :items="customizationNavItems"
                :label="trans('Personalización')"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
