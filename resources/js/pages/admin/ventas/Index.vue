<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { Store, Truck } from 'lucide-vue-next';
import { reactive } from 'vue';

interface VentaFila {
    id_venta: number;
    numero: string;
    fecha: string | null;
    cliente: string | null;
    libros: number;
    total: number;
    estado: string | null;
    entrega: 'tienda' | 'domicilio';
    estado_envio: string | null;
}

interface Filtros {
    q: string;
    estado: string;
    entrega: string;
    desde: string;
    hasta: string;
}

const props = defineProps<{
    ventas: Paginado<VentaFila>;
    resumen: { cantidad: number; monto: number };
    filtros: Filtros;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Ventas', href: '/admin/ventas' },
];

const form = reactive<Filtros>({ ...props.filtros });

function filtrar() {
    const datos = Object.fromEntries(Object.entries(form).filter(([, valor]) => valor !== ''));
    router.get(route('admin.ventas.index'), datos, { preserveState: true, preserveScroll: true });
}

function limpiar() {
    router.get(route('admin.ventas.index'));
}
</script>

<template>
    <Head title="Ventas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Ventas</h1>

            <form class="grid gap-2 rounded-xl border p-3 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="N.º de pedido o cliente" class="lg:col-span-2" />
                <Input v-model="form.desde" type="date" aria-label="Desde" />
                <Input v-model="form.hasta" type="date" aria-label="Hasta" />
                <select v-model="form.estado" :class="claseSelect" aria-label="Estado">
                    <option value="">Todos los estados</option>
                    <option value="COMPLETADA">Completadas</option>
                    <option value="ANULADA">Anuladas</option>
                </select>
                <select v-model="form.entrega" :class="claseSelect" aria-label="Entrega">
                    <option value="">Toda entrega</option>
                    <option value="tienda">Retiro en tienda</option>
                    <option value="domicilio">Envío a domicilio</option>
                </select>
                <div class="flex gap-2 lg:col-span-6">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" variant="outline" @click="limpiar">Limpiar</Button>
                </div>
            </form>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Ventas completadas (con estos filtros)</p>
                    <p class="mt-1 text-2xl font-semibold">{{ resumen.cantidad.toLocaleString('es-SV') }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Monto vendido (sin envío)</p>
                    <p class="mt-1 text-2xl font-semibold">{{ dinero(resumen.monto) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Pedido</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2 text-right">Libros</th>
                            <th class="px-3 py-2">Entrega</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="venta in ventas.data" :key="venta.id_venta" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.ventas.show', venta.id_venta)" class="font-mono font-medium hover:underline">#{{ venta.numero }}</Link>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ venta.fecha }}</td>
                            <td class="px-3 py-2">{{ venta.cliente }}</td>
                            <td class="px-3 py-2 text-right">{{ venta.libros }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <Truck v-if="venta.entrega === 'domicilio'" class="h-4 w-4 text-muted-foreground" />
                                    <Store v-else class="h-4 w-4 text-muted-foreground" />
                                    <EstadoPedido v-if="venta.estado_envio" :estado="venta.estado_envio" />
                                    <span v-else class="text-xs text-muted-foreground">Tienda</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ dinero(venta.total) }}</td>
                            <td class="px-3 py-2"><EstadoPedido :estado="venta.estado" /></td>
                        </tr>
                        <tr v-if="!ventas.data.length">
                            <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">No hay ventas con esos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="ventas" />
        </div>
    </AppLayout>
</template>