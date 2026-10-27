<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { dinero } from '@/lib/tienda';
import type { Paginado } from '@/types/tienda';
import { Head, Link } from '@inertiajs/vue3';
import { Store, Truck, Eye } from 'lucide-vue-next';

interface Pedido {
    id_venta: number;
    numero: string;
    fecha: string | null;
    libros: number;
    total: number;
    estado: string | null;
    entrega: 'tienda' | 'domicilio';
    estado_envio: string | null;
}

defineProps<{ pedidos: Paginado<Pedido> }>();
</script>

<template>
    <Head title="Mis pedidos" />

    <TiendaLayout>
        <div class="mb-6 flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">Mis pedidos</h1>
            <Link :href="route('cliente.devoluciones')" class="text-sm text-muted-foreground hover:underline">Ver mis devoluciones</Link>
        </div>

        <div v-if="!pedidos.data.length" class="rounded-lg border p-10 text-center">
            <p class="text-muted-foreground">Todavía no has hecho ninguna compra.</p>
            <Button as-child class="mt-4">
                <Link :href="route('catalogo.index')">Ir al catálogo</Link>
            </Button>
        </div>

        <template v-else>
            <ul class="divide-y rounded-lg border">
                <li v-for="pedido in pedidos.data" :key="pedido.id_venta" class="flex flex-wrap items-center gap-x-6 gap-y-2 p-4">
                    <div class="min-w-32">
                        <Link :href="route('cliente.pedidos.show', pedido.id_venta)" class="font-mono font-semibold hover:underline">#{{ pedido.numero }}</Link>
                        <p class="text-sm text-muted-foreground">{{ pedido.fecha }}</p>
                    </div>

                    <p class="text-sm text-muted-foreground">{{ pedido.libros }} {{ pedido.libros === 1 ? 'libro' : 'libros' }}</p>

                    <div class="flex items-center gap-2 text-sm">
                        <Truck v-if="pedido.entrega === 'domicilio'" class="h-4 w-4 text-muted-foreground" />
                        <Store v-else class="h-4 w-4 text-muted-foreground" />
                        <span>{{ pedido.entrega === 'domicilio' ? 'Envío a domicilio' : 'Retiro en tienda' }}</span>
                        <EstadoPedido v-if="pedido.estado_envio" :estado="pedido.estado_envio" />
                    </div>

                    <div class="ml-auto flex items-center gap-3">
                        <EstadoPedido :estado="pedido.estado" />
                        <p class="font-semibold">{{ dinero(pedido.total) }}</p>
                    </div>
                    <Button as-child variant="outline" size="sm" title="Ver detalles">
                        <Link :href="route('cliente.pedidos.show', pedido.id_venta)" >
                            <Eye class="h-4 w-4"/>
                        </Link>
                    </Button>
                </li>
            </ul>

            <nav v-if="pedidos.last_page > 1" class="mt-6 flex items-center justify-center gap-3">
                <Button :disabled="!pedidos.prev_page_url" variant="outline" size="sm" as-child>
                    <Link v-if="pedidos.prev_page_url" :href="pedidos.prev_page_url">Anterior</Link>
                    <span v-else>Anterior</span>
                </Button>
                <span class="text-sm text-muted-foreground">Página {{ pedidos.current_page }} de {{ pedidos.last_page }}</span>
                <Button :disabled="!pedidos.next_page_url" variant="outline" size="sm" as-child>
                    <Link v-if="pedidos.next_page_url" :href="pedidos.next_page_url">Siguiente</Link>
                    <span v-else>Siguiente</span>
                </Button>
            </nav>
        </template>
    </TiendaLayout>
</template>