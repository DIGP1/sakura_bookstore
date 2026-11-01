<script setup lang="ts">
import Disponibilidad from '@/components/tienda/Disponibilidad.vue';
import LibroPortada from '@/components/tienda/LibroPortada.vue';
import PrecioLibro from '@/components/tienda/PrecioLibro.vue';
import { Button } from '@/components/ui/button';
import type { LibroTarjeta } from '@/types/tienda';
import { Link, router } from '@inertiajs/vue3';
import { ShoppingCart } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ libro: LibroTarjeta; cargarInmediatamente?: boolean }>();

const agregando = ref(false);

function agregar() {
    router.post(
        route('carrito.items.store'),
        { id_producto: props.libro.id_producto, cantidad: 1 },
        {
            preserveScroll: true,
            onStart: () => (agregando.value = true),
            onFinish: () => (agregando.value = false),
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-2 rounded-lg border p-3">
        <Link :href="route('catalogo.show', libro.id_producto)" class="block">
            <LibroPortada
                :titulo="libro.nombre"
                :autores="libro.autores"
                :src="`/storage/portadas/${libro.id_producto}.jpg`"
                :cargar-inmediatamente="cargarInmediatamente"
            />
        </Link>

        <div class="flex flex-1 flex-col gap-1">
            <Link :href="route('catalogo.show', libro.id_producto)" class="line-clamp-2 text-sm font-medium hover:underline">
                {{ libro.nombre }}
            </Link>
            <p class="line-clamp-1 text-xs text-muted-foreground">{{ libro.autores || 'Autor desconocido' }}</p>
            <p v-if="libro.oferta" class="line-clamp-1 text-xs text-pink-700 dark:text-pink-400">{{ libro.oferta }}</p>
        </div>

        <PrecioLibro :precio="libro.precio" :precio-final="libro.precio_final" :descuento="libro.descuento" />

        <div class="flex items-center justify-between gap-2">
            <Disponibilidad :estado="libro.disponibilidad" :stock="libro.stock" />
            <Button
                size="sm"
                variant="outline"
                :disabled="libro.disponibilidad === 'agotado' || agregando"
                aria-label="Agregar al carrito"
                @click="agregar"
            >
                <ShoppingCart class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
