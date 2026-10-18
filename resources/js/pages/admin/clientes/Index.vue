<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion, Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface ClienteFila {
    id_cliente: number;
    nombre: string;
    email: string | null;
    telefono: string | null;
    ubicacion: string;
    compras: number;
    gastado: number;
    ultima_compra: string | null;
    con_cuenta: boolean;
}

interface Filtros {
    q: string;
    departamento: number | string;
    orden: string;
}

const props = defineProps<{
    clientes: Paginado<ClienteFila>;
    filtros: Filtros;
    departamentos: Opcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Clientes', href: '/admin/clientes' },
];

const form = reactive<Filtros>({ ...props.filtros });

function filtrar() {
    const datos: Record<string, string | number> = {};
    if (form.q) datos.q = form.q;
    if (form.departamento) datos.departamento = form.departamento;
    if (form.orden && form.orden !== 'recientes') datos.orden = form.orden;
    router.get(route('admin.clientes.index'), datos, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Clientes</h1>

            <form class="flex flex-wrap gap-2" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="Nombre, correo o teléfono" class="w-full sm:w-64" />
                <select v-model="form.departamento" :class="[claseSelect, 'w-full sm:w-52']" @change="filtrar">
                    <option value="">Todos los departamentos</option>
                    <option v-for="d in departamentos" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                </select>
                <select v-model="form.orden" :class="[claseSelect, 'w-full sm:w-48']" @change="filtrar">
                    <option value="recientes">Más recientes</option>
                    <option value="compras">Más compras</option>
                    <option value="gasto">Mayor gasto</option>
                </select>
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Teléfono</th>
                            <th class="px-3 py-2">Ubicación</th>
                            <th class="px-3 py-2 text-right">Compras</th>
                            <th class="px-3 py-2 text-right">Gastado</th>
                            <th class="px-3 py-2">Última compra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in clientes.data" :key="c.id_cliente" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.clientes.show', c.id_cliente)" class="font-medium hover:underline">{{ c.nombre }}</Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ c.email ?? 'Sin correo' }}<span v-if="c.con_cuenta"> · tiene cuenta</span>
                                </p>
                            </td>
                            <td class="px-3 py-2">{{ c.telefono ?? '—' }}</td>
                            <td class="px-3 py-2">{{ c.ubicacion }}</td>
                            <td class="px-3 py-2 text-right">{{ c.compras }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(c.gastado) }}</td>
                            <td class="px-3 py-2">{{ c.ultima_compra ?? 'Nunca' }}</td>
                        </tr>
                        <tr v-if="!clientes.data.length">
                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">No hay clientes con esos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="clientes" />
        </div>
    </AppLayout>
</template>