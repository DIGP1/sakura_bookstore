<script setup lang="ts">
import { Button } from '@/components/ui/button';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { dinero } from '@/lib/tienda';
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';

defineProps<{
    venta: {
        numero: string;
        fecha: string | null;
        lineas: { nombre: string; cantidad: number; precio_unitario: number; descuento_aplicado: number; subtotal: number }[];
        subtotal: number;
        descuento_global: number;
        oferta_global: string | null;
        total: number;
        costo_envio: number;
        total_a_pagar: number;
        envio: {
            tipo: string | null;
            direccion: string;
            ubicacion: string;
            receptor: string;
            telefono: string;
            fecha_estimada: string | null;
        } | null;
    };
}>();
</script>

<template>
    <Head title="Compra realizada" />

    <TiendaLayout>
        <div class="mx-auto max-w-2xl">
            <div class="flex flex-col items-center text-center">
                <CircleCheck class="h-14 w-14 text-green-600" />
                <h1 class="mt-3 text-2xl font-semibold">¡Compra realizada!</h1>
                <p class="mt-1 text-muted-foreground">Tu número de pedido es</p>
                <p class="mt-1 font-mono text-3xl font-semibold tracking-wider">#{{ venta.numero }}</p>
                <p v-if="venta.fecha" class="mt-1 text-sm text-muted-foreground">{{ venta.fecha }}</p>
            </div>

            <section class="mt-8 rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Detalle</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="(linea, i) in venta.lineas" :key="i" class="flex justify-between gap-3">
                        <span>
                            {{ linea.cantidad }} × {{ linea.nombre }}
                            <span v-if="linea.descuento_aplicado > 0" class="text-xs text-pink-700 dark:text-pink-400">
                                (-{{ dinero(linea.descuento_aplicado) }} c/u)
                            </span>
                        </span>
                        <span class="shrink-0">{{ dinero(linea.subtotal) }}</span>
                    </li>
                </ul>
                <dl class="mt-3 space-y-2 border-t pt-3 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ dinero(venta.subtotal) }}</dd></div>
                    <div v-if="venta.descuento_global > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>{{ venta.oferta_global ?? 'Descuento' }}</dt><dd>-{{ dinero(venta.descuento_global) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Envío</dt><dd>{{ venta.envio ? dinero(venta.costo_envio) : 'Retiro en tienda' }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-semibold"><dt>Total pagado</dt><dd>{{ dinero(venta.total_a_pagar) }}</dd></div>
                </dl>
            </section>

            <section v-if="venta.envio" class="mt-4 rounded-lg border p-4 text-sm">
                <h2 class="mb-3 font-semibold">Envío a domicilio ({{ venta.envio.tipo }})</h2>
                <p>{{ venta.envio.direccion }}</p>
                <p class="text-muted-foreground">{{ venta.envio.ubicacion }}</p>
                <p class="mt-2">Recibe: {{ venta.envio.receptor }} · {{ venta.envio.telefono }}</p>
                <p v-if="venta.envio.fecha_estimada" class="mt-2 font-medium">Entrega estimada: {{ venta.envio.fecha_estimada }}</p>
            </section>
            <p v-else class="mt-4 rounded-lg border p-4 text-sm">
                Podés pasar a retirar tu pedido a la tienda presentando el número <strong>#{{ venta.numero }}</strong>.
            </p>

            <div class="mt-8 flex justify-center">
                <Button as-child>
                    <Link :href="route('catalogo.index')">Seguir comprando</Link>
                </Button>
            </div>
        </div>
    </TiendaLayout>
</template>