<script setup lang="ts">
import LibroCard from '@/components/tienda/LibroCard.vue';
import { Button } from '@/components/ui/button';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import type { LibroTarjeta, Opcion } from '@/types/tienda';
import { Head, Link } from '@inertiajs/vue3';
import { Tag } from 'lucide-vue-next';

interface OfertaVigente {
    id: number;
    nombre: string;
    descripcion: string | null;
    fecha_fin: string | null;
}

defineProps<{
    destacados: LibroTarjeta[];
    enOferta: LibroTarjeta[];
    ofertas: OfertaVigente[];
    categorias: Opcion[];
}>();
</script>

<template>
    <Head title="Inicio" />

    <TiendaLayout>
        <section class="rounded-xl bg-gradient-to-br from-pink-100 to-rose-50 px-6 py-10 dark:from-pink-950 dark:to-background">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Libros para cada lector</h1>
            <p class="mt-3 max-w-xl text-muted-foreground">Literatura salvadoreña, clásicos, novela, textos escolares y más.</p>
            <Button as-child class="mt-6">
                <Link :href="route('catalogo.index')">Ver catálogo</Link>
            </Button>
        </section>

        <section v-if="ofertas.length" class="mt-10">
            <h2 class="mb-4 text-xl font-semibold">Ofertas vigentes</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="oferta in ofertas" :key="oferta.id" class="flex gap-3 rounded-lg border p-4">
                    <Tag class="mt-0.5 h-5 w-5 shrink-0 text-pink-600" />
                    <div>
                        <p class="font-medium">{{ oferta.nombre }}</p>
                        <p v-if="oferta.descripcion" class="text-sm text-muted-foreground">{{ oferta.descripcion }}</p>
                        <p v-if="oferta.fecha_fin" class="mt-1 text-xs text-muted-foreground">Válida hasta el {{ oferta.fecha_fin }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="enOferta.length" class="mt-10">
            <h2 class="mb-4 text-xl font-semibold">Libros en oferta</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <LibroCard v-for="libro in enOferta" :key="libro.id_producto" :libro="libro" />
            </div>
        </section>

        <section class="mt-10">
            <h2 class="mb-4 text-xl font-semibold">Destacados</h2>
            <div v-if="destacados.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <LibroCard v-for="libro in destacados" :key="libro.id_producto" :libro="libro" />
            </div>
            <p v-else class="text-sm text-muted-foreground">Todavía no hay libros para mostrar.</p>
        </section>

        <section v-if="categorias.length" class="mt-10">
            <h2 class="mb-4 text-xl font-semibold">Categorías</h2>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="categoria in categorias"
                    :key="categoria.id"
                    :href="route('catalogo.index', { categoria: categoria.id })"
                    class="rounded-full border px-3 py-1 text-sm hover:bg-accent"
                >
                    {{ categoria.nombre }}
                </Link>
            </div>
        </section>
    </TiendaLayout>
</template>