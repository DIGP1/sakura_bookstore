<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    venta: {
        id_venta: number;
        numero: string;
        fecha: string | null;
        estado: string | null;
        cliente: { nombre: string | null; email: string | null; telefono: string | null; ubicacion: string };
        lineas: { id_producto: number; nombre: string; cantidad: number; precio_unitario: number; descuento_aplicado: number; oferta: string | null; subtotal: number }[];
        subtotal: number;
        descuento_global: number;
        oferta_global: string | null;
        total: number;
        costo_envio: number;
        total_cobrado: number;
        envio: {
            estado: string | null;
            tipo: string | null;
            direccion: string;
            ubicacion: string;
            receptor: string;
            telefono: string;
            fecha_estimada: string | null;
        } | null;
        devoluciones: { id_devolucion: number; fecha: string | null; estado: string | null }[];
        puede_anular: boolean;
        motivo_no_anular: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Ventas', href: '/admin/ventas' },
    { title: `#${props.venta.numero}`, href: '#' },
];

const procesando = ref(false);

function anular() {
    if (!confirm(`¿Anular la venta #${props.venta.numero}? Los libros van a regresar al stock y no se puede deshacer.`)) return;
    router.post(route('admin.ventas.cancel', props.venta.id_venta), {}, {
        preserveScroll: true,
        onStart: () => (procesando.value = true),
        onFinish: () => (procesando.value = false),
    });
}
</script>

<template>
    <Head :title="`Venta #${venta.numero}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.ventas.index')" class="text-sm text-muted-foreground hover:underline">← Volver a ventas</Link>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-xl font-semibold">Venta #{{ venta.numero }}</h1>
                <EstadoPedido :estado="venta.estado" />
                <span class="text-sm text-muted-foreground">{{ venta.fecha }}</span>

                <div class="ml-auto">
                    <Button v-if="venta.puede_anular" variant="outline" class="text-red-600" :disabled="procesando" @click="anular">
                        <Ban class="h-4 w-4" /> Anular venta
                    </Button>
                    <p v-else-if="venta.estado === 'COMPLETADA' && venta.motivo_no_anular" class="max-w-xs text-right text-xs text-muted-foreground">
                        {{ venta.motivo_no_anular }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
                <section class="rounded-xl border p-4">
                    <h2 class="mb-3 font-medium">Libros</h2>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="py-2">Libro</th>
                                <th class="py-2 text-right">Cant.</th>
                                <th class="py-2 text-right">Precio</th>
                                <th class="py-2 text-right">Desc. c/u</th>
                                <th class="py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="linea in venta.lineas" :key="linea.id_producto">
                                <td class="py-2">
                                    <p class="font-medium">{{ linea.nombre }}</p>
                                    <p v-if="linea.oferta" class="text-xs text-pink-700 dark:text-pink-400">{{ linea.oferta }}</p>
                                </td>
                                <td class="py-2 text-right">{{ linea.cantidad }}</td>
                                <td class="py-2 text-right">{{ dinero(linea.precio_unitario) }}</td>
                                <td class="py-2 text-right">{{ linea.descuento_aplicado > 0 ? `−${dinero(linea.descuento_aplicado)}` : '—' }}</td>
                                <td class="py-2 text-right">{{ dinero(linea.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="ml-auto mt-3 max-w-xs space-y-1 border-t pt-3 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ dinero(venta.subtotal) }}</dd></div>
                        <div v-if="venta.descuento_global > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                            <dt>{{ venta.oferta_global ?? 'Descuento' }}</dt><dd>−{{ dinero(venta.descuento_global) }}</dd>
                        </div>
                        <div class="flex justify-between"><dt>Total de la venta</dt><dd>{{ dinero(venta.total) }}</dd></div>
                        <div class="flex justify-between"><dt>Envío</dt><dd>{{ venta.envio ? dinero(venta.costo_envio) : 'Retiro en tienda' }}</dd></div>
                        <div class="flex justify-between border-t pt-1 font-semibold"><dt>Total cobrado</dt><dd>{{ dinero(venta.total_cobrado) }}</dd></div>
                    </dl>
                </section>

                <aside class="flex flex-col gap-4">
                    <section class="rounded-xl border p-4 text-sm">
                        <h2 class="mb-2 font-medium">Cliente</h2>
                        <p class="font-medium">{{ venta.cliente.nombre }}</p>
                        <p class="text-muted-foreground">{{ venta.cliente.email }}</p>
                        <p class="text-muted-foreground">{{ venta.cliente.telefono }}</p>
                        <p class="text-muted-foreground">{{ venta.cliente.ubicacion }}</p>
                    </section>

                    <section v-if="venta.envio" class="rounded-xl border p-4 text-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="font-medium">Envío {{ venta.envio.tipo?.toLowerCase() }}</h2>
                            <EstadoPedido :estado="venta.envio.estado" />
                        </div>
                        <p>{{ venta.envio.direccion }}</p>
                        <p class="text-muted-foreground">{{ venta.envio.ubicacion }}</p>
                        <p class="mt-1">Recibe: {{ venta.envio.receptor }} · {{ venta.envio.telefono }}</p>
                        <p class="mt-1 text-muted-foreground">Estimado: {{ venta.envio.fecha_estimada }}</p>
                    </section>

                    <section v-if="venta.devoluciones.length" class="rounded-xl border p-4 text-sm">
                        <h2 class="mb-2 font-medium">Devoluciones</h2>
                        <ul class="space-y-1">
                            <li v-for="d in venta.devoluciones" :key="d.id_devolucion" class="flex items-center justify-between">
                                <span>#{{ d.id_devolucion }} · {{ d.fecha }}</span>
                                <EstadoPedido :estado="d.estado" />
                            </li>
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>