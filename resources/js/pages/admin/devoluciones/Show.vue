<script setup lang="ts">
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';

const props = defineProps<{
    devolucion: {
        id_devolucion: number;
        fecha: string | null;
        estado: string | null;
        motivo: string | null;
        tipo_reembolso: string | null;
        id_venta: number;
        numero_pedido: string;
        fecha_pedido: string | null;
        cliente: { nombre: string | null; email: string | null; telefono: string | null };
        entrega: string;
        lineas: { id_detalle_devolucion: number; nombre: string; cantidad: number; comprada: number; monto: number; reingresa_stock: boolean }[];
        total: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Devoluciones', href: '/admin/devoluciones' },
    { title: `#${props.devolucion.id_devolucion}`, href: '#' },
];

const porRevisar = props.devolucion.estado === 'SOLICITADA';

// Por defecto todos los libros regresan al stock; el admin desmarca los que vinieron dañados.
const form = useForm({
    reingresa: Object.fromEntries(props.devolucion.lineas.map((l) => [l.id_detalle_devolucion, l.reingresa_stock])) as Record<number, boolean>,
});

function aprobar() {
    if (!confirm('¿Aprobar la devolución? Los libros marcados van a regresar al stock y no se puede deshacer.')) return;
    form.post(route('admin.devoluciones.approve', props.devolucion.id_devolucion), { preserveScroll: true });
}

function rechazar() {
    if (!confirm('¿Rechazar la devolución?')) return;
    router.post(route('admin.devoluciones.reject', props.devolucion.id_devolucion), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Devolución #${devolucion.id_devolucion}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.devoluciones.index')" class="text-sm text-muted-foreground hover:underline">← Volver a devoluciones</Link>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold">Devolución #{{ devolucion.id_devolucion }}</h1>
                <EstadoPedido :estado="devolucion.estado" />
                <span class="text-sm text-muted-foreground">{{ devolucion.fecha }}</span>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
                <section class="rounded-xl border p-4">
                    <h2 class="mb-1 font-medium">Libros devueltos</h2>
                    <p v-if="porRevisar" class="mb-3 text-sm text-muted-foreground">
                        Desmarcá los libros que vinieron dañados: esos no regresan al stock.
                    </p>
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="py-2">Libro</th>
                                <th class="py-2 text-right">Devuelve</th>
                                <th class="py-2 text-right">Reembolso</th>
                                <th class="py-2 text-center">Regresa al stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="linea in devolucion.lineas" :key="linea.id_detalle_devolucion">
                                <td class="py-2 font-medium">{{ linea.nombre }}</td>
                                <td class="py-2 text-right">{{ linea.cantidad }} de {{ linea.comprada }}</td>
                                <td class="py-2 text-right">{{ dinero(linea.monto) }}</td>
                                <td class="py-2 text-center">
                                    <input
                                        v-if="porRevisar"
                                        v-model="form.reingresa[linea.id_detalle_devolucion]"
                                        type="checkbox"
                                        class="h-4 w-4"
                                        :aria-label="`${linea.nombre} regresa al stock`"
                                    />
                                    <span v-else>{{ linea.reingresa_stock ? 'Sí' : 'No' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-3 flex justify-between border-t pt-3 font-semibold">
                        <span>Total a reembolsar</span>
                        <span>{{ dinero(devolucion.total) }}</span>
                    </div>

                    <div v-if="porRevisar" class="mt-4 flex flex-wrap gap-2">
                        <Button class="bg-green-600 text-white hover:bg-green-700" :disabled="form.processing" @click="aprobar">
                            <Check class="h-4 w-4" /> Aprobar devolución
                        </Button>
                        <Button variant="outline" class="text-red-600" :disabled="form.processing" @click="rechazar">
                            <X class="h-4 w-4" /> Rechazar
                        </Button>
                    </div>
                </section>

                <aside class="flex flex-col gap-4 text-sm">
                    <section class="rounded-xl border p-4">
                        <h2 class="mb-2 font-medium">Solicitud</h2>
                        <p><span class="text-muted-foreground">Motivo:</span> {{ devolucion.motivo }}</p>
                        <p><span class="text-muted-foreground">Reembolso:</span> {{ devolucion.tipo_reembolso }}</p>
                    </section>

                    <section class="rounded-xl border p-4">
                        <h2 class="mb-2 font-medium">Pedido</h2>
                        <Link :href="route('admin.ventas.show', devolucion.id_venta)" class="font-mono hover:underline">#{{ devolucion.numero_pedido }}</Link>
                        <p class="text-muted-foreground">{{ devolucion.fecha_pedido }} · {{ devolucion.entrega }}</p>
                    </section>

                    <section class="rounded-xl border p-4">
                        <h2 class="mb-2 font-medium">Cliente</h2>
                        <p class="font-medium">{{ devolucion.cliente.nombre }}</p>
                        <p class="text-muted-foreground">{{ devolucion.cliente.email }}</p>
                        <p class="text-muted-foreground">{{ devolucion.cliente.telefono }}</p>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>