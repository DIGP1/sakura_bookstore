<script setup lang="ts">
import Disponibilidad from '@/components/tienda/Disponibilidad.vue';
import LibroPortada from '@/components/tienda/LibroPortada.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { dinero } from '@/lib/tienda';
import type { SharedData } from '@/types';
import type { LineaCarrito, ResumenCarrito } from '@/types/tienda';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Minus, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ resumen: ResumenCarrito }>();

const page = usePage<SharedData>();
const usuario = computed(() => page.props.auth.user);

function cambiar(linea: LineaCarrito, cantidad: number) {
    if (cantidad < 1 || cantidad === linea.cantidad) return;
    router.patch(route('carrito.items.update', linea.id_producto), { cantidad }, { preserveScroll: true });
}

function quitar(linea: LineaCarrito) {
    router.delete(route('carrito.items.destroy', linea.id_producto), { preserveScroll: true });
}
</script>

<template>
    <Head title="Carrito" />

    <TiendaLayout>
        <h1 class="mb-6 text-2xl font-semibold">Tu carrito</h1>

        <div v-if="!resumen.lineas.length" class="rounded-lg border p-10 text-center">
            <p class="text-muted-foreground">Tu carrito está vacío.</p>
            <Button as-child class="mt-4">
                <Link :href="route('catalogo.index')">Ir al catálogo</Link>
            </Button>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-[1fr_320px]">
            <ul class="divide-y rounded-lg border">
                <li v-for="linea in resumen.lineas" :key="linea.id_producto" class="flex gap-4 p-4">
                    <Link :href="route('catalogo.show', linea.id_producto)" class="w-16 shrink-0">
                        <LibroPortada :titulo="linea.nombre" :autores="linea.autores" :src="route('catalogo.portada', linea.id_producto)" />
                    </Link>

                    <div class="flex flex-1 flex-col gap-2">
                        <div class="flex justify-between gap-4">
                            <div>
                                <Link :href="route('catalogo.show', linea.id_producto)" class="font-medium hover:underline">{{ linea.nombre }}</Link>
                                <p class="text-sm text-muted-foreground">{{ linea.autores }}</p>
                                <p v-if="linea.oferta" class="text-xs text-pink-700 dark:text-pink-400">{{ linea.oferta }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ dinero(linea.subtotal) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ dinero(linea.precio_final) }} c/u
                                    <span v-if="linea.descuento > 0" class="line-through">{{ dinero(linea.precio) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1">
                                <Button variant="outline" size="icon" class="h-8 w-8" :disabled="linea.cantidad <= 1" aria-label="Quitar uno" @click="cambiar(linea, linea.cantidad - 1)">
                                    <Minus class="h-3 w-3" />
                                </Button>
                                <Input
                                    :model-value="linea.cantidad"
                                    type="number"
                                    min="1"
                                    :max="linea.stock"
                                    class="h-8 w-16 text-center"
                                    aria-label="Cantidad"
                                    @change="cambiar(linea, Number(($event.target as HTMLInputElement).value))"
                                />
                                <Button variant="outline" size="icon" class="h-8 w-8" :disabled="linea.cantidad >= linea.stock" aria-label="Agregar uno" @click="cambiar(linea, linea.cantidad + 1)">
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                            <Disponibilidad :estado="linea.disponibilidad" :stock="linea.stock" />
                            <Button variant="ghost" size="sm" class="text-red-600" @click="quitar(linea)">
                                <Trash2 class="h-4 w-4" />
                                Quitar
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>

            <aside class="h-fit rounded-lg border p-4">
                <h2 class="mb-4 font-semibold">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Libros</dt><dd>{{ resumen.cantidad_libros }}</dd></div>
                    <div v-if="resumen.ahorro_ofertas > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>Ahorro por ofertas</dt><dd>-{{ dinero(resumen.ahorro_ofertas) }}</dd>
                    </div>
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ dinero(resumen.subtotal) }}</dd></div>
                    <div v-if="resumen.descuento_global > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>{{ resumen.oferta_global }}</dt><dd>-{{ dinero(resumen.descuento_global) }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ dinero(resumen.total) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-muted-foreground">El costo de envío se calcula en el checkout.</p>

                <Button as-child class="mt-4 w-full">
                    <Link v-if="usuario?.rol === 'cliente'" :href="route('checkout')">Continuar con la compra</Link>
                    <Link v-else-if="!usuario" :href="route('login')">Iniciá sesión para comprar</Link>
                    <span v-else>Solo los clientes pueden comprar</span>
                </Button>
            </aside>
        </div>
    </TiendaLayout>
</template>
