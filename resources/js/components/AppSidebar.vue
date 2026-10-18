<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain, { type GrupoMenu } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    ClipboardList,
    LayoutGrid,
    Percent,
    Receipt,
    ShoppingBag,
    Store,
    Tags,
    Truck,
    Undo2,
    UserRound,
    UsersRound,
    Warehouse,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Menú del panel. Las secciones se van llenando en los pasos del bloque 7.
const grupos: GrupoMenu[] = [
    {
        titulo: 'General',
        items: [{ title: 'Inicio', href: '/admin', icon: LayoutGrid }],
    },
    {
        titulo: 'Catálogo',
        items: [
            { title: 'Libros', href: '/admin/libros', icon: BookOpen },
            { title: 'Autores', href: '/admin/autores', icon: UsersRound },
            { title: 'Editoriales', href: '/admin/editoriales', icon: Building2 },
            { title: 'Categorías', href: '/admin/categorias', icon: Tags },
            { title: 'Ofertas', href: '/admin/ofertas', icon: Percent },
        ],
    },
    {
        titulo: 'Inventario',
        items: [
            { title: 'Proveedores', href: '/admin/proveedores', icon: Warehouse },
            { title: 'Compras', href: '/admin/compras', icon: ShoppingBag },
            { title: 'Kardex', href: '/admin/kardex', icon: ClipboardList },
        ],
    },
    {
        titulo: 'Ventas',
        items: [
            { title: 'Ventas', href: '/admin/ventas', icon: Receipt },
            { title: 'Envíos', href: '/admin/envios', icon: Truck },
            { title: 'Devoluciones', href: '/admin/devoluciones', icon: Undo2 },
            { title: 'Clientes', href: '/admin/clientes', icon: UserRound },
        ],
    },
];

const footerNavItems: NavItem[] = [{ title: 'Ver la tienda', href: '/', icon: Store }];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('admin.dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :grupos="grupos" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>