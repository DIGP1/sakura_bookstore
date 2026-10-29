<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Eye } from 'lucide-vue-next';

interface DevolucionFila {
    id_devolucion: number;
    fecha: string | null;
    numero_pedido: string;
    cliente: string | null;
    motivo: string | null;
    libros: number;
    monto: number;
    estado: string | null;
}

const props = defineProps<{
    devoluciones: Paginado<DevolucionFila>;
    conteo: { SOLICITADA: number; APROBADA: number; RECHAZADA: number };
    filtros: { estado: string; q: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Devoluciones', href: '/admin/devoluciones' },
];

const pestanas = [
    { valor: 'SOLICITADA', texto: 'Por revisar' },
    { valor: 'APROBADA', texto: 'Aprobadas' },
    { valor: 'RECHAZADA', texto: 'Rechazadas' },
    { valor: 'TODAS', texto: 'Todas' },
] as const;

const busqueda = ref(props.filtros.q);

function ir(estado: string, q = busqueda.value) {
    const datos: Record<string, string> = { estado };
    if (q) datos.q = q;
    router.get(route('admin.devoluciones.index'), datos, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Devoluciones" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Devoluciones</h1>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in pestanas"
                    :key="p.valor"
                    type="button"
                    :class="['rounded-full border px-4 py-1.5 text-sm hover:bg-accent', filtros.estado === p.valor ? 'border-primary bg-primary text-primary-foreground hover:bg-primary' : '']"
                    @click="ir(p.valor)"
                >
                    {{ p.texto }}
                    <span v-if="p.valor !== 'TODAS'" class="ml-1 opacity-75">({{ conteo[p.valor] }})</span>
                </button>
            </div>

            <form class="flex gap-2" @submit.prevent="ir(filtros.estado)">
                <Input v-model="busqueda" type="search" placeholder="N.º de devolución, pedido o cliente" class="w-full sm:w-72" />
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Pedido</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Motivo</th>
                            <th class="px-3 py-2 text-right">Libros</th>
                            <th class="px-3 py-2 text-right">Reembolso</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Ver detalles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in devoluciones.data" :key="d.id_devolucion" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.devoluciones.show', d.id_devolucion)" class="font-medium hover:underline">#{{ d.id_devolucion }}</Link>
                            </td>
                            <td class="px-3 py-2">{{ d.fecha }}</td>
                            <td class="px-3 py-2 font-mono text-xs">#{{ d.numero_pedido }}</td>
                            <td class="px-3 py-2">{{ d.cliente }}</td>
                            <td class="px-3 py-2">{{ d.motivo }}</td>
                            <td class="px-3 py-2 text-right">{{ d.libros }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(d.monto) }}</td>
                            <td class="px-3 py-2"><EstadoPedido :estado="d.estado" /></td>
                            <td class="px-3 py-2">
                                <Button as-child variant="outline" size="sm" title="Ver detalles">
                                    <Link :href="route('admin.devoluciones.show', d.id_devolucion)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!devoluciones.data.length">
                            <td colspan="8" class="px-3 py-8 text-center text-muted-foreground">No hay devoluciones aquí.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="devoluciones" />
        </div>
    </AppLayout>
</template>