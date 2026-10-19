<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link } from '@inertiajs/vue3';

interface VentaCliente {
    id_venta: number;
    numero: string;
    fecha: string | null;
    libros: number;
    total: number;
    entrega: string;
    estado: string | null;
}

const props = defineProps<{
    cliente: {
        id_cliente: number;
        nombre: string;
        email: string | null;
        telefono: string | null;
        direccion: string | null;
        ubicacion: string;
        fecha_registro: string | null;
        con_cuenta: boolean;
    };
    estadisticas: {
        compras: number;
        gastado: number;
        ticket_promedio: number;
        primera: string | null;
        ultima: string | null;
        devoluciones: number;
    };
    ventas: Paginado<VentaCliente>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Clientes', href: '/admin/clientes' },
    { title: props.cliente.nombre, href: '#' },
];
</script>

<template>
    <Head :title="cliente.nombre" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.clientes.index')" class="text-sm text-muted-foreground hover:underline">← Volver a clientes</Link>

            <div class="grid gap-4 lg:grid-cols-[320px_1fr]">
                <section class="h-fit rounded-xl border p-4 text-sm">
                    <h1 class="text-lg font-semibold">{{ cliente.nombre }}</h1>
                    <p class="text-muted-foreground">{{ cliente.con_cuenta ? 'Tiene cuenta en la tienda' : 'Cliente sin cuenta' }}</p>
                    <dl class="mt-3 space-y-2">
                        <div><dt class="text-muted-foreground">Correo</dt><dd>{{ cliente.email ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Teléfono</dt><dd>{{ cliente.telefono ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Dirección</dt><dd>{{ cliente.direccion ?? '—' }}</dd></div>
                        <div><dt class="text-muted-foreground">Ubicación</dt><dd>{{ cliente.ubicacion }}</dd></div>
                        <div><dt class="text-muted-foreground">Cliente desde</dt><dd>{{ cliente.fecha_registro }}</dd></div>
                    </dl>
                </section>

                <div class="flex flex-col gap-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border p-4">
                            <p class="text-sm text-muted-foreground">Compras</p>
                            <p class="mt-1 text-2xl font-semibold">{{ estadisticas.compras }}</p>
                            <p class="text-xs text-muted-foreground">{{ estadisticas.devoluciones }} devolución(es)</p>
                        </div>
                        <div class="rounded-xl border p-4">
                            <p class="text-sm text-muted-foreground">Total gastado</p>
                            <p class="mt-1 text-2xl font-semibold">{{ dinero(estadisticas.gastado) }}</p>
                            <p class="text-xs text-muted-foreground">Ticket promedio: {{ dinero(estadisticas.ticket_promedio) }}</p>
                        </div>
                        <div class="rounded-xl border p-4">
                            <p class="text-sm text-muted-foreground">Última compra</p>
                            <p class="mt-1 text-2xl font-semibold">{{ estadisticas.ultima ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">Primera: {{ estadisticas.primera ?? '—' }}</p>
                        </div>
                    </div>

                    <section class="rounded-xl border">
                        <h2 class="border-b px-4 py-3 font-medium">Historial de compras</h2>
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2">Pedido</th>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2 text-right">Libros</th>
                                    <th class="px-4 py-2">Entrega</th>
                                    <th class="px-4 py-2 text-right">Total</th>
                                    <th class="px-4 py-2">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="v in ventas.data" :key="v.id_venta">
                                    <td class="px-4 py-2">
                                        <Link :href="route('admin.ventas.show', v.id_venta)" class="font-mono hover:underline">#{{ v.numero }}</Link>
                                    </td>
                                    <td class="px-4 py-2">{{ v.fecha }}</td>
                                    <td class="px-4 py-2 text-right">{{ v.libros }}</td>
                                    <td class="px-4 py-2">{{ v.entrega }}</td>
                                    <td class="px-4 py-2 text-right">{{ dinero(v.total) }}</td>
                                    <td class="px-4 py-2"><EstadoPedido :estado="v.estado" /></td>
                                </tr>
                                <tr v-if="!ventas.data.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Este cliente todavía no ha comprado.</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="border-t px-4 py-3">
                            <Paginacion :datos="ventas" />
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>