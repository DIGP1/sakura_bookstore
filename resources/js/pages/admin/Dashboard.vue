<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { PackageX, Receipt, Truck, Undo2 } from 'lucide-vue-next';

defineProps<{
    resumen: {
        ventas_hoy: number;
        ingresos_hoy: number;
        envios_pendientes: number;
        devoluciones_por_revisar: number;
        libros_stock_bajo: number;
    };
    envios: { id_venta: number; numero: string; cliente: string | null; destino: string; estado: string | null; fecha_estimada: string | null; atrasado: boolean }[];
    devoluciones: { id_devolucion: number; cliente: string | null; motivo: string | null; fecha: string | null; monto: number }[];
    stockBajo: { id_producto: number; nombre: string; stock: number; stock_minimo: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/admin' }];
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        Ventas de hoy <Receipt class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold">{{ dinero(resumen.ingresos_hoy) }}</p>
                    <p class="text-xs text-muted-foreground">{{ resumen.ventas_hoy }} {{ resumen.ventas_hoy === 1 ? 'venta' : 'ventas' }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        Envíos por despachar <Truck class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold">{{ resumen.envios_pendientes }}</p>
                    <p class="text-xs text-muted-foreground">Pendientes y en camino</p>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        Devoluciones por revisar <Undo2 class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold">{{ resumen.devoluciones_por_revisar }}</p>
                    <p class="text-xs text-muted-foreground">Solicitadas por clientes</p>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        Libros con poco stock <PackageX class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-2xl font-semibold" :class="resumen.libros_stock_bajo > 0 ? 'text-amber-600' : ''">{{ resumen.libros_stock_bajo }}</p>
                    <p class="text-xs text-muted-foreground">En o bajo el stock mínimo</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border p-4">
                    <h2 class="mb-3 font-semibold">Envíos por despachar</h2>
                    <ul v-if="envios.length" class="divide-y text-sm">
                        <li v-for="envio in envios" :key="envio.id_venta" class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <p class="font-medium"><span class="font-mono">#{{ envio.numero }}</span> · {{ envio.cliente }}</p>
                                <p class="text-xs text-muted-foreground">{{ envio.destino }}</p>
                            </div>
                            <div class="text-right">
                                <EstadoPedido :estado="envio.estado" />
                                <p class="mt-1 text-xs" :class="envio.atrasado ? 'font-medium text-red-600' : 'text-muted-foreground'">
                                    {{ envio.atrasado ? 'Atrasado · ' : '' }}{{ envio.fecha_estimada }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No hay envíos pendientes.</p>
                </section>

                <section class="rounded-xl border p-4">
                    <h2 class="mb-3 font-semibold">Devoluciones por revisar</h2>
                    <ul v-if="devoluciones.length" class="divide-y text-sm">
                        <li v-for="devolucion in devoluciones" :key="devolucion.id_devolucion" class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <p class="font-medium">#{{ devolucion.id_devolucion }} · {{ devolucion.cliente }}</p>
                                <p class="text-xs text-muted-foreground">{{ devolucion.motivo }} · {{ devolucion.fecha }}</p>
                            </div>
                            <p class="font-medium">{{ dinero(devolucion.monto) }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No hay devoluciones por revisar.</p>
                </section>

                <section class="rounded-xl border p-4 lg:col-span-2">
                    <h2 class="mb-3 font-semibold">Libros con poco stock</h2>
                    <ul v-if="stockBajo.length" class="grid gap-x-6 text-sm sm:grid-cols-2">
                        <li v-for="libro in stockBajo" :key="libro.id_producto" class="flex items-center justify-between gap-3 border-b py-2">
                            <span class="line-clamp-1">{{ libro.nombre }}</span>
                            <span :class="['shrink-0 font-medium', libro.stock === 0 ? 'text-red-600' : 'text-amber-600']">
                                {{ libro.stock }} / mín. {{ libro.stock_minimo }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Todos los libros tienen stock suficiente.</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>