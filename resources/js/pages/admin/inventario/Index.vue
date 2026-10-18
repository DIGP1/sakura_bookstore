<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface LibroInventario {
    id_producto: number;
    nombre: string;
    isbn: string;
    stock: number;
    stock_minimo: number;
    costo_promedio: number;
    valor: number;
    ultimo_movimiento: string | null;
}

const props = defineProps<{
    libros: Paginado<LibroInventario>;
    filtros: { q: string; stock: string };
    totales: { unidades: number; valor: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Inventario', href: '/admin/inventario' },
];

const form = reactive({ ...props.filtros });

function filtrar() {
    const datos: Record<string, string> = {};
    if (form.q) datos.q = form.q;
    if (form.stock) datos.stock = form.stock;
    router.get(route('admin.inventario.index'), datos, { preserveState: true, preserveScroll: true });
}

function claseStock(libro: LibroInventario) {
    if (libro.stock === 0) return 'text-red-600 font-medium';
    if (libro.stock <= libro.stock_minimo) return 'text-amber-600 font-medium';
    return '';
}
</script>

<template>
    <Head title="Inventario" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">Inventario</h1>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Unidades en bodega</p>
                    <p class="mt-1 text-2xl font-semibold">{{ totales.unidades.toLocaleString('es-SV') }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Valor del inventario (al costo)</p>
                    <p class="mt-1 text-2xl font-semibold">{{ dinero(totales.valor) }}</p>
                </div>
            </div>

            <form class="flex flex-wrap gap-2" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="Buscar por título o ISBN" class="w-full sm:w-64" />
                <select v-model="form.stock" :class="[claseSelect, 'w-full sm:w-44']" @change="filtrar">
                    <option value="">Todo el stock</option>
                    <option value="bajo">Stock bajo</option>
                    <option value="agotado">Agotados</option>
                </select>
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Libro</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2 text-right">Mínimo</th>
                            <th class="px-3 py-2 text-right">Costo prom.</th>
                            <th class="px-3 py-2 text-right">Valor</th>
                            <th class="px-3 py-2">Último movimiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="libro in libros.data" :key="libro.id_producto" class="hover:bg-muted/30">
                            <td class="px-3 py-2">
                                <Link :href="route('admin.inventario.show', libro.id_producto)" class="font-medium hover:underline">{{ libro.nombre }}</Link>
                                <p class="font-mono text-xs text-muted-foreground">{{ libro.isbn }}</p>
                            </td>
                            <td class="px-3 py-2 text-right" :class="claseStock(libro)">{{ libro.stock }}</td>
                            <td class="px-3 py-2 text-right text-muted-foreground">{{ libro.stock_minimo }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(libro.costo_promedio) }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(libro.valor) }}</td>
                            <td class="px-3 py-2 text-muted-foreground">{{ libro.ultimo_movimiento ?? 'Sin movimientos' }}</td>
                        </tr>
                        <tr v-if="!libros.data.length">
                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">No hay libros con esos filtros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="libros" />
        </div>
    </AppLayout>
</template>