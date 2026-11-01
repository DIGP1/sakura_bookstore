<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

export interface GrupoMenu {
    titulo: string;
    items: NavItem[];
}

defineProps<{ grupos: GrupoMenu[] }>();

const page = usePage<SharedData>();

// Una sección queda marcada si la URL actual es ella o una de sus subpáginas.
function activo(href: string): boolean {
    const ruta = page.url.split('?')[0];
    return href === '/admin' ? ruta === '/admin' : ruta === href || ruta.startsWith(`${href}/`);
}
</script>

<template>
    <SidebarGroup v-for="grupo in grupos" :key="grupo.titulo" class="px-2 py-0">
        <SidebarGroupLabel>{{ grupo.titulo }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in grupo.items" :key="item.title">
                <SidebarMenuButton as-child :is-active="activo(item.href)" :tooltip="item.title">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>