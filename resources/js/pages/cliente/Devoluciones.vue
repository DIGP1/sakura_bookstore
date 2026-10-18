<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { dinero } from '@/lib/tienda';
import type { Paginado } from '@/types/tienda';
import { Head, Link } from '@inertiajs/vue3';

interface Devolucion {
    id_devolucion: number;
    id_venta: number;
    numero_pedido: string;
    fecha: string | null;
    estado: string | null;
    motivo: string | null;
    tipo_reembolso: string | null;
    libros: { nombre: string; cantidad: number }[];
    reembolso: number;
}

defineProps<{ devoluciones: Paginado<Devolucion> }>();
</script>

<template>
    <Head title="Mis devoluciones" />

    <TiendaLayout>
        <div class="mb-6 flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">Mis devoluciones</h1>
            <Link :href="route('cliente.pedidos')" class="text-sm text-muted-foreground hover:underline">Ver mis pedidos</Link>
        </div>

        <div v-if="!devoluciones.data.length" class="rounded-lg border p-10 text-center">
            <p class="text-muted-foreground">No has solicitado ninguna devolución.</p>
            <p class="mt-1 text-sm text-muted-foreground">Podés pedirla desde el detalle de cualquier pedido de los últimos 30 días.</p>
        </div>

        <template v-else>
            <ul class="divide-y rounded-lg border">
                <li v-for="devolucion in devoluciones.data" :key="devolucion.id_devolucion" class="flex flex-col gap-2 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="font-semibold">Devolución #{{ devolucion.id_devolucion }}</p>
                        <EstadoPedido :estado="devolucion.estado" />
                        <span class="text-sm text-muted-foreground">{{ devolucion.fecha }}</span>
                        <p class="ml-auto font-semibold">{{ dinero(devolucion.reembolso) }}</p>
                    </div>

                    <ul class="text-sm">
                        <li v-for="(libro, i) in devolucion.libros" :key="i">{{ libro.cantidad }} × {{ libro.nombre }}</li>
                    </ul>

                    <p class="text-xs text-muted-foreground">
                        Motivo: {{ devolucion.motivo }} · Reembolso: {{ devolucion.tipo_reembolso }} · Pedido
                        <Link :href="route('cliente.pedidos.show', devolucion.id_venta)" class="font-mono hover:underline">#{{ devolucion.numero_pedido }}</Link>
                    </p>
                </li>
            </ul>

            <nav v-if="devoluciones.last_page > 1" class="mt-6 flex items-center justify-center gap-3">
                <Button :disabled="!devoluciones.prev_page_url" variant="outline" size="sm" as-child>
                    <Link v-if="devoluciones.prev_page_url" :href="devoluciones.prev_page_url">Anterior</Link>
                    <span v-else>Anterior</span>
                </Button>
                <span class="text-sm text-muted-foreground">Página {{ devoluciones.current_page }} de {{ devoluciones.last_page }}</span>
                <Button :disabled="!devoluciones.next_page_url" variant="outline" size="sm" as-child>
                    <Link v-if="devoluciones.next_page_url" :href="devoluciones.next_page_url">Siguiente</Link>
                    <span v-else>Siguiente</span>
                </Button>
            </nav>
        </template>
    </TiendaLayout>
</template>