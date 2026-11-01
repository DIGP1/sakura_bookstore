<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    ClipboardList,
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
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pendientes: { envios: number; devoluciones: number; stock_bajo: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/admin' }];

// Nombre del usuario que inició sesión
const page = usePage<SharedData>();
const nombre = computed(() => page.props.auth.user.name.split(' ')[0]);

// Saludo según la hora y fecha de hoy en español
const hora = new Date().getHours();
const saludo = hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches';
const fecha = new Date().toLocaleDateString('es-SV', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// Accesos rápidos: los mismos módulos de la barra lateral.
// "aviso" muestra cuántas cosas pendientes hay en ese módulo.
type Acceso = { titulo: string; descripcion: string; href: string; icono: LucideIcon; aviso?: number };

const grupos = computed<{ titulo: string; accesos: Acceso[] }[]>(() => [
    {
        titulo: 'Ventas',
        accesos: [
            { titulo: 'Ventas', descripcion: 'Historial de compras de los clientes', href: '/admin/ventas', icono: Receipt },
            { titulo: 'Envíos', descripcion: 'Despachar y actualizar pedidos', href: '/admin/envios', icono: Truck, aviso: props.pendientes.envios },
            { titulo: 'Devoluciones', descripcion: 'Aprobar o rechazar solicitudes', href: '/admin/devoluciones', icono: Undo2, aviso: props.pendientes.devoluciones },
            { titulo: 'Clientes', descripcion: 'Datos y compras de cada cliente', href: '/admin/clientes', icono: UserRound },
        ],
    },
    {
        titulo: 'Catálogo',
        accesos: [
            { titulo: 'Libros', descripcion: 'Agregar y editar títulos', href: '/admin/libros', icono: BookOpen },
            { titulo: 'Autores', descripcion: 'Escritores del catálogo', href: '/admin/autores', icono: UsersRound },
            { titulo: 'Editoriales', descripcion: 'Casas editoriales', href: '/admin/editoriales', icono: Building2 },
            { titulo: 'Categorías', descripcion: 'Géneros de los libros', href: '/admin/categorias', icono: Tags },
            { titulo: 'Ofertas', descripcion: 'Descuentos y promociones', href: '/admin/ofertas', icono: Percent },
        ],
    },
    {
        titulo: 'Inventario',
        accesos: [
            { titulo: 'Proveedores', descripcion: 'Empresas que surten libros', href: '/admin/proveedores', icono: Warehouse },
            { titulo: 'Compras', descripcion: 'Registrar y recibir mercadería', href: '/admin/compras', icono: ShoppingBag },
            { titulo: 'Inventario', descripcion: 'Stock y kardex de movimientos', href: '/admin/inventario', icono: ClipboardList, aviso: props.pendientes.stock_bajo },
        ],
    },
]);
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-4">
            <!-- Bienvenida -->
            <section class="flex flex-col gap-4 rounded-xl border bg-muted/40 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm capitalize text-muted-foreground">{{ fecha }}</p>
                    <h1 class="mt-1 text-2xl font-semibold">{{ saludo }}, {{ nombre }} 👋</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Bienvenido al panel de administración de Sakura Bookstore. ¿Qué querés gestionar hoy?</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        href="/admin/libros"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <BookOpen class="h-4 w-4" /> Gestionar libros
                    </Link>
                    <Link href="/" class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted">
                        <Store class="h-4 w-4" /> Ver la tienda
                    </Link>
                </div>
            </section>

            <!-- Accesos rápidos por grupo (igual que la barra lateral) -->
            <section v-for="grupo in grupos" :key="grupo.titulo">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ grupo.titulo }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <Link
                        v-for="acceso in grupo.accesos"
                        :key="acceso.href"
                        :href="acceso.href"
                        class="group relative flex items-start gap-3 rounded-xl border p-4 transition hover:border-primary/40 hover:bg-muted/50"
                    >
                        <span class="rounded-lg bg-muted p-2 group-hover:bg-background">
                            <component :is="acceso.icono" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium">{{ acceso.titulo }}</p>
                            <p class="text-xs text-muted-foreground">{{ acceso.descripcion }}</p>
                        </div>
                        <!-- Aviso de pendientes (solo si hay) -->
                        <span
                            v-if="acceso.aviso"
                            class="absolute right-3 top-3 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white"
                            :title="`${acceso.aviso} pendiente(s)`"
                        >
                            {{ acceso.aviso }}
                        </span>
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>