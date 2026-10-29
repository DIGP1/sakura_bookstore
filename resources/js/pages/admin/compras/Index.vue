<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion, Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Eye } from 'lucide-vue-next';
import { reactive } from 'vue';

interface Compra {
    id_compra: number;
    fecha: string | null;
    proveedor: string | null;
    numero_factura: string;
    unidades: number;
    total: number;
    estado: string | null;
}

const props = defineProps<{
    compras: Paginado<Compra>;
    filtros: { estado: string; proveedor: number | string; q: string };
    proveedores: Opcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Compras', href: '/admin/compras' },
];

const form = reactive({ ...props.filtros });

function filtrar() {
    const datos: Record<string, string | number> = {};
    if (form.estado) datos.estado = form.estado;
    if (form.proveedor) datos.proveedor = form.proveedor;
    if (form.q) datos.q = form.q;
    router.get(route('admin.compras.index'), datos, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Compras" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Compras a proveedores</h1>
                <Button as-child>
                    <Link :href="route('admin.compras.create')"><Plus class="h-4 w-4" /> Nueva compra</Link>
                </Button>
            </div>

            <form class="flex flex-wrap gap-2" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="N.º de factura" class="w-full sm:w-48" />
                <select v-model="form.proveedor" :class="[claseSelect, 'w-full sm:w-56']" @change="filtrar">
                    <option value="">Todos los proveedores</option>
                    <option v-for="proveedor in proveedores" :key="proveedor.id" :value="proveedor.id">{{ proveedor.nombre }}</option>
                </select>
                <select v-model="form.estado" :class="[claseSelect, 'w-full sm:w-40']" @change="filtrar">
                    <option value="">Todos los estados</option>
                    <option value="PENDIENTE">Pendientes</option>
                    <option value="RECIBIDA">Recibidas</option>
                    <option value="ANULADA">Anuladas</option>
                </select>
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Proveedor</th>
                            <th class="px-3 py-2">Factura</th>
                            <th class="px-3 py-2 text-right">Unidades</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">  Ver detalles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="compra in compras.data" :key="compra.id_compra" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.compras.show', compra.id_compra)" class="font-medium hover:underline">#{{ compra.id_compra }}</Link>
                            </td>
                            <td class="px-3 py-2">{{ compra.fecha }}</td>
                            <td class="px-3 py-2">{{ compra.proveedor }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ compra.numero_factura }}</td>
                            <td class="px-3 py-2 text-right">{{ compra.unidades }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(compra.total) }}</td>
                            <td class="px-3 py-2"><EstadoPedido :estado="compra.estado" /></td>
                            <td class="px-3 py-2">
                                <Button as-child variant="outline" size="sm" title="Ver detalles">
                                    <Link :href="route('admin.compras.show', compra.id_compra)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!compras.data.length">
                            <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">No hay compras con esos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="compras" />
        </div>
    </AppLayout>
</template>