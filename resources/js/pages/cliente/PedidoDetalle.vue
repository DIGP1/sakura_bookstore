<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { dinero } from '@/lib/tienda';
import { Head, Link } from '@inertiajs/vue3';
import { Store } from 'lucide-vue-next';

interface Movimiento {
    estado: string | null;
    fecha: string | null;
    observacion: string | null;
}

defineProps<{
    pedido: {
        id_venta: number;
        numero: string;
        fecha: string | null;
        estado: string | null;
        puede_devolver: boolean;
        lineas: { id_producto: number; nombre: string; cantidad: number; precio_unitario: number; descuento_aplicado: number; subtotal: number }[];
        subtotal: number;
        descuento_global: number;
        oferta_global: string | null;
        costo_envio: number;
        total_a_pagar: number;
        envio: {
            estado: string | null;
            tipo: string | null;
            proveedor: string | null;
            direccion: string;
            ubicacion: string;
            receptor: string;
            telefono: string;
            fecha_estimada: string | null;
            fecha_entrega: string | null;
            historial: Movimiento[];
        } | null;
    };
}>();
</script>

<template>
    <Head :title="`Pedido #${pedido.numero}`" />

    <TiendaLayout>
        <Link :href="route('cliente.pedidos')" class="text-sm text-muted-foreground hover:underline">← Volver a mis pedidos</Link>

        <div class="mb-6 mt-2 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold">Pedido #{{ pedido.numero }}</h1>
            <EstadoPedido :estado="pedido.estado" />
            <span class="text-sm text-muted-foreground">{{ pedido.fecha }}</span>
            <Link
                v-if="pedido.puede_devolver"
                :href="route('cliente.devoluciones.create', pedido.id_venta)"
                class="ml-auto rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
            >
                Solicitar devolución
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
            <section class="h-fit rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Libros</h2>
                <ul class="divide-y text-sm">
                    <li v-for="linea in pedido.lineas" :key="linea.id_producto" class="flex justify-between gap-3 py-2">
                        <div>
                            <Link :href="route('catalogo.show', linea.id_producto)" class="font-medium hover:underline">{{ linea.nombre }}</Link>
                            <p class="text-xs text-muted-foreground">
                                {{ linea.cantidad }} × {{ dinero(linea.precio_unitario - linea.descuento_aplicado) }}
                                <span v-if="linea.descuento_aplicado > 0" class="line-through">{{ dinero(linea.precio_unitario) }}</span>
                            </p>
                        </div>
                        <span class="shrink-0 font-medium">{{ dinero(linea.subtotal) }}</span>
                    </li>
                </ul>
                <dl class="mt-3 space-y-2 border-t pt-3 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ dinero(pedido.subtotal) }}</dd></div>
                    <div v-if="pedido.descuento_global > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>{{ pedido.oferta_global ?? 'Descuento' }}</dt><dd>-{{ dinero(pedido.descuento_global) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Envío</dt><dd>{{ pedido.envio ? dinero(pedido.costo_envio) : 'Retiro en tienda' }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-semibold"><dt>Total pagado</dt><dd>{{ dinero(pedido.total_a_pagar) }}</dd></div>
                </dl>
            </section>

            <aside class="flex flex-col gap-4">
                <section v-if="pedido.envio" class="rounded-lg border p-4 text-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="font-semibold">Envío {{ pedido.envio.tipo?.toLowerCase() }}</h2>
                        <EstadoPedido :estado="pedido.envio.estado" />
                    </div>
                    <p>{{ pedido.envio.direccion }}</p>
                    <p class="text-muted-foreground">{{ pedido.envio.ubicacion }}</p>
                    <p class="mt-2">Recibe: {{ pedido.envio.receptor }} · {{ pedido.envio.telefono }}</p>
                    <p v-if="pedido.envio.proveedor" class="text-muted-foreground">Lo lleva: {{ pedido.envio.proveedor }}</p>
                    <p class="mt-2 font-medium">
                        <template v-if="pedido.envio.fecha_entrega">Entregado el {{ pedido.envio.fecha_entrega }}</template>
                        <template v-else-if="pedido.envio.fecha_estimada">Entrega estimada: {{ pedido.envio.fecha_estimada }}</template>
                    </p>

                    <h3 class="mb-3 mt-5 font-semibold">Seguimiento</h3>
                    <ol class="relative ml-2 border-l">
                        <li v-for="(movimiento, i) in pedido.envio.historial" :key="i" class="mb-4 ml-4 last:mb-0">
                            <span
                                :class="[
                                    'absolute -left-1.5 mt-1 h-3 w-3 rounded-full border-2 border-background',
                                    i === pedido.envio.historial.length - 1 ? 'bg-primary' : 'bg-muted-foreground/40',
                                ]"
                            />
                            <EstadoPedido :estado="movimiento.estado" />
                            <p class="mt-1 text-xs text-muted-foreground">{{ movimiento.fecha }}</p>
                            <p v-if="movimiento.observacion" class="text-xs">{{ movimiento.observacion }}</p>
                        </li>
                    </ol>
                </section>

                <section v-else class="flex gap-3 rounded-lg border p-4 text-sm">
                    <Store class="h-5 w-5 shrink-0 text-muted-foreground" />
                    <p>Retiro en tienda. Presentá el número <strong>#{{ pedido.numero }}</strong> para recoger tu pedido.</p>
                </section>
            </aside>
        </div>
    </TiendaLayout>
</template>