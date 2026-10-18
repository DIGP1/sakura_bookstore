<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion, Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface EnvioFila {
    id_envio: number;
    id_venta: number;
    numero: string;
    cliente: string | null;
    destino: string;
    tipo: string | null;
    proveedor: string | null;
    fecha_estimada: string | null;
    estado: string | null;
    atrasado: boolean;
}

interface Filtros {
    estado: string;
    proveedor: number | string;
    atrasados: boolean;
    q: string;
}

const props = defineProps<{
    envios: Paginado<EnvioFila>;
    conteo: Record<string, number>;
    filtros: Filtros;
    proveedores: Opcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Envíos', href: '/admin/envios' },
];

const nombres: Record<string, string> = {
    PENDIENTE: 'Pendientes',
    EN_CAMINO: 'En camino',
    ENTREGADO: 'Entregados',
    FALLIDO: 'Fallidos',
    DEVUELTO: 'Devueltos',
};

const form = reactive<Filtros>({ ...props.filtros });

function filtrar() {
    const datos: Record<string, string | number> = {};
    if (form.estado) datos.estado = form.estado;
    if (form.proveedor) datos.proveedor = form.proveedor;
    if (form.atrasados) datos.atrasados = 1;
    if (form.q) datos.q = form.q;
    router.get(route('admin.envios.index'), datos, { preserveState: true, preserveScroll: true });
}

function verEstado(estado: string) {
    form.estado = form.estado === estado ? '' : estado;
    filtrar();
}
</script>

<template>
    <Head title="Envíos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Envíos</h1>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <button
                    v-for="(total, estado) in conteo"
                    :key="estado"
                    type="button"
                    :class="['rounded-xl border p-3 text-left hover:bg-accent', form.estado === estado ? 'border-primary ring-1 ring-primary' : '']"
                    @click="verEstado(String(estado))"
                >
                    <p class="text-xs text-muted-foreground">{{ nombres[estado] }}</p>
                    <p class="text-xl font-semibold">{{ total.toLocaleString('es-SV') }}</p>
                </button>
            </div>

            <form class="flex flex-wrap items-center gap-2" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="N.º de pedido, cliente o receptor" class="w-full sm:w-64" />
                <select v-model="form.proveedor" :class="[claseSelect, 'w-full sm:w-56']" @change="filtrar">
                    <option value="">Todos los proveedores</option>
                    <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.atrasados" type="checkbox" class="h-4 w-4" @change="filtrar" />
                    Solo atrasados
                </label>
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Pedido</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Destino</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Proveedor</th>
                            <th class="px-3 py-2">Estimado</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="envio in envios.data" :key="envio.id_envio" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.envios.show', envio.id_envio)" class="font-mono font-medium hover:underline">#{{ envio.numero }}</Link>
                            </td>
                            <td class="px-3 py-2">{{ envio.cliente }}</td>
                            <td class="px-3 py-2">{{ envio.destino }}</td>
                            <td class="px-3 py-2">{{ envio.tipo }}</td>
                            <td class="px-3 py-2">{{ envio.proveedor }}</td>
                            <td class="whitespace-nowrap px-3 py-2" :class="envio.atrasado ? 'font-medium text-red-600' : ''">
                                {{ envio.fecha_estimada }}<span v-if="envio.atrasado"> · atrasado</span>
                            </td>
                            <td class="px-3 py-2"><EstadoPedido :estado="envio.estado" /></td>
                        </tr>
                        <tr v-if="!envios.data.length">
                            <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">No hay envíos con esos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="envios" />
        </div>
    </AppLayout>
</template>