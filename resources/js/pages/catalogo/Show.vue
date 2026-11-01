<script setup lang="ts">
import Disponibilidad from '@/components/tienda/Disponibilidad.vue';
import LibroCard from '@/components/tienda/LibroCard.vue';
import LibroPortada from '@/components/tienda/LibroPortada.vue';
import PrecioLibro from '@/components/tienda/PrecioLibro.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import type { FichaLibro, LibroTarjeta } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShoppingCart } from 'lucide-vue-next';

const props = defineProps<{
    producto: FichaLibro;
    mismoAutor: LibroTarjeta[];
    mismaCategoria: LibroTarjeta[];
}>();

const form = useForm({ id_producto: props.producto.id_producto, cantidad: 1 });

function agregar() {
    form.post(route('carrito.items.store'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="producto.nombre" />

    <TiendaLayout>
        <Link :href="route('catalogo.index')" class="text-sm text-muted-foreground hover:underline">← Volver al catálogo</Link>

        <div class="mt-4 grid gap-8 md:grid-cols-[280px_1fr]">
            <div class="mx-auto w-full max-w-[280px]">
                <LibroPortada
                    :titulo="producto.nombre"
                    :autores="producto.autores"
                    :src="`/storage/portadas/${producto.id_producto}.jpg`"
                    grande
                />
            </div>

            <div class="flex flex-col gap-4">
                <div>
                    <h1 class="text-2xl font-semibold sm:text-3xl">{{ producto.nombre }}</h1>
                    <p class="mt-1 text-muted-foreground">{{ producto.autores || 'Autor desconocido' }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="categoria in producto.categorias"
                        :key="categoria.id"
                        :href="route('catalogo.index', { categoria: categoria.id })"
                        class="rounded-full border px-3 py-0.5 text-xs hover:bg-accent"
                    >
                        {{ categoria.nombre }}
                    </Link>
                </div>

                <PrecioLibro :precio="producto.precio" :precio-final="producto.precio_final" :descuento="producto.descuento" grande />
                <p v-if="producto.oferta" class="-mt-2 text-sm text-pink-700 dark:text-pink-400">{{ producto.oferta }}</p>

                <Disponibilidad :estado="producto.disponibilidad" :stock="producto.stock" class="self-start" />

                <form v-if="producto.disponibilidad !== 'agotado'" class="flex items-center gap-2" @submit.prevent="agregar">
                    <Input v-model.number="form.cantidad" type="number" min="1" :max="producto.stock" class="w-20" aria-label="Cantidad" />
                    <Button type="submit" :disabled="form.processing">
                        <ShoppingCart class="h-4 w-4" />
                        Agregar al carrito
                    </Button>
                </form>
                <p v-else class="text-sm text-muted-foreground">Este libro está agotado por ahora.</p>

                <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-2 rounded-lg border p-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">ISBN</dt>
                        <dd>{{ producto.isbn }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Editorial</dt>
                        <dd>{{ producto.editorial ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Año</dt>
                        <dd>{{ producto.anio_publicacion ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Idioma</dt>
                        <dd>{{ producto.idioma ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Formato</dt>
                        <dd>{{ producto.formato ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Páginas</dt>
                        <dd>{{ producto.num_paginas ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <section v-if="mismoAutor.length" class="mt-12">
            <h2 class="mb-4 text-xl font-semibold">Más del mismo autor</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <LibroCard v-for="(libro, indice) in mismoAutor" :key="libro.id_producto" :libro="libro" :cargar-inmediatamente="indice < 4" />
            </div>
        </section>

        <section v-if="mismaCategoria.length" class="mt-12">
            <h2 class="mb-4 text-xl font-semibold">De la misma categoría</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <LibroCard v-for="(libro, indice) in mismaCategoria" :key="libro.id_producto" :libro="libro" :cargar-inmediatamente="indice < 4" />
            </div>
        </section>
    </TiendaLayout>
</template>
