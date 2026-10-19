<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { PackageCheck, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    compra: {
        id_compra: number;
        fecha: string | null;
        proveedor: string | null;
        numero_factura: string;
        estado: string | null;
        lineas: { id_producto: number; nombre: string; isbn: string; cantidad: number; costo_unitario: number; subtotal: number; stock_actual: number }[];
        total: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Compras', href: '/admin/compras' },
    { title: `#${props.compra.id_compra}`, href: '#' },
];

const procesando = ref(false);

function recibir() {
    if (!confirm('¿Confirmás que llegó toda la mercadería? Se va a sumar al stock y no se puede deshacer.')) return;
    router.post(route('admin.compras.receive', props.compra.id_compra), {}, {
        preserveScroll: true,
        onStart: () => (procesando.value = true),
        onFinish: () => (procesando.value = false),
    });
}

function anular() {
    if (!confirm('¿Anular esta compra?')) return;
    router.post(route('admin.compras.cancel', props.compra.id_compra), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Compra #${compra.id_compra}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.compras.index')" class="text-sm text-muted-foreground hover:underline">← Volver a compras</Link>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold">Compra #{{ compra.id_compra }}</h1>
                <EstadoPedido :estado="compra.estado" />

                <div v-if="compra.estado === 'PENDIENTE'" class="ml-auto flex gap-2">
                    <Button :disabled="procesando" @click="recibir"><PackageCheck class="h-4 w-4" /> Marcar como recibida</Button>
                    <Button variant="outline" class="text-red-600" @click="anular"><XCircle class="h-4 w-4" /> Anular</Button>
                </div>
            </div>

            <dl class="grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-3">
                <div><dt class="text-muted-foreground">Proveedor</dt><dd class="font-medium">{{ compra.proveedor }}</dd></div>
                <div><dt class="text-muted-foreground">Factura</dt><dd class="font-mono">{{ compra.numero_factura }}</dd></div>
                <div><dt class="text-muted-foreground">Fecha</dt><dd>{{ compra.fecha }}</dd></div>
            </dl>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Libro</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">Costo c/u</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                            <th class="px-3 py-2 text-right">Stock actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="linea in compra.lineas" :key="linea.id_producto">
                            <td class="px-3 py-2">
                                <p class="font-medium">{{ linea.nombre }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ linea.isbn }}</p>
                            </td>
                            <td class="px-3 py-2 text-right">{{ linea.cantidad }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(linea.costo_unitario) }}</td>
                            <td class="px-3 py-2 text-right">{{ dinero(linea.subtotal) }}</td>
                            <td class="px-3 py-2 text-right">{{ linea.stock_actual }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td colspan="3" class="px-3 py-2 text-right">Total</td>
                            <td class="px-3 py-2 text-right">{{ dinero(compra.total) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p v-if="compra.estado === 'PENDIENTE'" class="text-sm text-muted-foreground">
                Mientras esté pendiente, el stock de estos libros no cambia.
            </p>
        </div>
    </AppLayout>
</template>