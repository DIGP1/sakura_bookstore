<script setup lang="ts">
import LibroCard from '@/components/tienda/LibroCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { claseSelect } from '@/lib/tienda';
import type { LibroTarjeta, Opcion, Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Filtros {
    q: string;
    categoria: number | string;
    editorial: number | string;
    precio_min: number | string;
    precio_max: number | string;
    disponibles: boolean;
    orden: string;
}

const props = defineProps<{
    productos: Paginado<LibroTarjeta>;
    filtros: Filtros;
    categorias: Opcion[];
    editoriales: Opcion[];
}>();

const form = reactive<Filtros>({ ...props.filtros });

// Solo se mandan los filtros que tienen valor, para que la URL quede limpia.
function aplicar() {
    const datos: Record<string, string | number> = {};
    if (form.q) datos.q = form.q;
    if (form.categoria) datos.categoria = form.categoria;
    if (form.editorial) datos.editorial = form.editorial;
    if (form.precio_min !== '' && form.precio_min !== null) datos.precio_min = form.precio_min;
    if (form.precio_max !== '' && form.precio_max !== null) datos.precio_max = form.precio_max;
    if (form.disponibles) datos.disponibles = 1;
    if (form.orden && form.orden !== 'titulo') datos.orden = form.orden;

    router.get(route('catalogo.index'), datos, { preserveScroll: true });
}

function limpiar() {
    router.get(route('catalogo.index'));
}
</script>

<template>
    <Head title="Catálogo" />

    <TiendaLayout>
        <div class="flex flex-col gap-8 lg:flex-row">
            <aside class="lg:w-64 lg:shrink-0">
                <form class="flex flex-col gap-4 rounded-lg border p-4" @submit.prevent="aplicar">
                    <h2 class="font-semibold">Filtros</h2>

                    <div class="grid gap-2">
                        <Label for="q">Buscar</Label>
                        <Input id="q" v-model="form.q" type="search" placeholder="Título, autor o ISBN" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="categoria">Categoría</Label>
                        <select id="categoria" v-model="form.categoria" :class="claseSelect">
                            <option value="">Todas</option>
                            <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">{{ categoria.nombre }}</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="editorial">Editorial</Label>
                        <select id="editorial" v-model="form.editorial" :class="claseSelect">
                            <option value="">Todas</option>
                            <option v-for="editorial in editoriales" :key="editorial.id" :value="editorial.id">{{ editorial.nombre }}</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label>Precio (USD)</Label>
                        <div class="flex items-center gap-2">
                            <Input v-model="form.precio_min" type="number" min="0" step="0.01" placeholder="Mín." />
                            <span class="text-muted-foreground">–</span>
                            <Input v-model="form.precio_max" type="number" min="0" step="0.01" placeholder="Máx." />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.disponibles" type="checkbox" class="h-4 w-4 rounded border-input" />
                        Solo disponibles
                    </label>

                    <div class="grid gap-2">
                        <Label for="orden">Ordenar por</Label>
                        <select id="orden" v-model="form.orden" :class="claseSelect">
                            <option value="titulo">Título (A-Z)</option>
                            <option value="precio_asc">Precio: menor a mayor</option>
                            <option value="precio_desc">Precio: mayor a menor</option>
                            <option value="recientes">Más recientes</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" class="flex-1">Aplicar</Button>
                        <Button type="button" variant="outline" @click="limpiar">Limpiar</Button>
                    </div>
                </form>
            </aside>

            <section class="flex-1">
                <div class="mb-4 flex items-baseline justify-between">
                    <h1 class="text-2xl font-semibold">Catálogo</h1>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="productos.total">{{ productos.from }}–{{ productos.to }} de {{ productos.total }} libros</template>
                    </p>
                </div>

                <div v-if="productos.data.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                    <LibroCard v-for="libro in productos.data" :key="libro.id_producto" :libro="libro" />
                </div>
                <p v-else class="rounded-lg border p-8 text-center text-muted-foreground">No encontramos libros con esos filtros.</p>

                <nav v-if="productos.last_page > 1" class="mt-8 flex items-center justify-center gap-3">
                    <Button :disabled="!productos.prev_page_url" variant="outline" size="sm" as-child>
                        <Link v-if="productos.prev_page_url" :href="productos.prev_page_url" preserve-scroll>Anterior</Link>
                        <span v-else>Anterior</span>
                    </Button>
                    <span class="text-sm text-muted-foreground">Página {{ productos.current_page }} de {{ productos.last_page }}</span>
                    <Button :disabled="!productos.next_page_url" variant="outline" size="sm" as-child>
                        <Link v-if="productos.next_page_url" :href="productos.next_page_url" preserve-scroll>Siguiente</Link>
                        <span v-else>Siguiente</span>
                    </Button>
                </nav>
            </section>
        </div>
    </TiendaLayout>
</template>