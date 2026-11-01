<script setup lang="ts">
import { confirmar } from '@/composables/useConfirmar';
import Paginacion from '@/components/admin/Paginacion.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion, Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface Libro {
    id_producto: number;
    isbn: string;
    nombre: string;
    autores: string;
    editorial: string | null;
    categorias: string;
    precio: number;
    stock: number;
    stock_minimo: number;
}

const props = defineProps<{
    libros: Paginado<Libro>;
    filtros: { q: string; categoria: number | string; stock: string };
    categorias: Opcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Libros', href: '/admin/libros' },
];

const form = reactive({ ...props.filtros });

function filtrar() {
    const datos: Record<string, string | number> = {};
    if (form.q) datos.q = form.q;
    if (form.categoria) datos.categoria = form.categoria;
    if (form.stock) datos.stock = form.stock;
    router.get(route('admin.libros.index'), datos, { preserveState: true, preserveScroll: true });
}

async function eliminar(libro: Libro) {
    if (!(await confirmar({ titulo: 'Eliminar libro', mensaje: `¿Eliminar "${libro.nombre}"? Esta acción no se puede deshacer.`, boton: 'Eliminar', peligro: true }))) return;
    router.delete(route('admin.libros.destroy', libro.id_producto), { preserveScroll: true });
}

function claseStock(libro: Libro) {
    if (libro.stock === 0) return 'text-red-600 font-medium';
    if (libro.stock <= libro.stock_minimo) return 'text-amber-600 font-medium';
    return '';
}
</script>

<template>
    <Head title="Libros" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Libros</h1>
                <Button as-child>
                    <Link :href="route('admin.libros.create')"><Plus class="h-4 w-4" /> Nuevo libro</Link>
                </Button>
            </div>

            <form class="flex flex-wrap gap-2" @submit.prevent="filtrar">
                <Input v-model="form.q" type="search" placeholder="Buscar por título o ISBN" class="w-full sm:w-64" />
                <select v-model="form.categoria" :class="[claseSelect, 'w-full sm:w-48']" @change="filtrar">
                    <option value="">Todas las categorías</option>
                    <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">{{ categoria.nombre }}</option>
                </select>
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
                            <th class="px-3 py-2">ISBN</th>
                            <th class="px-3 py-2">Editorial</th>
                            <th class="px-3 py-2 text-right">Precio</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="libro in libros.data" :key="libro.id_producto">
                            <td class="px-3 py-2">
                                <p class="font-medium">{{ libro.nombre }}</p>
                                <p class="text-xs text-muted-foreground">{{ libro.autores }} · {{ libro.categorias }}</p>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ libro.isbn }}</td>
                            <td class="px-3 py-2">{{ libro.editorial }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(libro.precio) }}</td>
                            <td class="px-3 py-2 text-right" :class="claseStock(libro)">
                                {{ libro.stock }} <span class="text-xs text-muted-foreground">/ {{ libro.stock_minimo }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" as-child :aria-label="`Editar ${libro.nombre}`">
                                        <Link :href="route('admin.libros.edit', libro.id_producto)"><Pencil class="h-4 w-4" /></Link>
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-red-600" :aria-label="`Eliminar ${libro.nombre}`" @click="eliminar(libro)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
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