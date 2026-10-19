<script setup lang="ts">
import Paginacion from '@/components/admin/Paginacion.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface Movimiento {
    id_movimiento: number;
    fecha: string;
    tipo: string;
    cantidad: number;
    costo_unitario: number;
    stock_resultante: number;
    observacion: string | null;
    origen: { texto: string; compra: number | null };
}

const props = defineProps<{
    libro: {
        id_producto: number;
        nombre: string;
        isbn: string;
        stock: number;
        stock_minimo: number;
        costo_promedio: number;
        valor: number;
        entradas: number;
        salidas: number;
    };
    movimientos: Paginado<Movimiento>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Inventario', href: '/admin/inventario' },
    { title: props.libro.nombre, href: '#' },
];

const tipos: Record<string, string> = {
    COMPRA: 'Compra',
    VENTA: 'Venta',
    DEVOLUCION: 'Devolución',
    ANULACION_VENTA: 'Anulación de venta',
    AJUSTE_POSITIVO: 'Ajuste (entrada)',
    AJUSTE_NEGATIVO: 'Ajuste (salida)',
};

const form = useForm({
    tipo: 'salida' as 'entrada' | 'salida',
    cantidad: 1,
    observacion: '',
});

function ajustar() {
    const verbo = form.tipo === 'entrada' ? 'sumar' : 'restar';
    if (!confirm(`¿${verbo[0].toUpperCase() + verbo.slice(1)} ${form.cantidad} unidad(es) al stock? El ajuste no se puede borrar.`)) return;

    form.post(route('admin.inventario.ajustar', props.libro.id_producto), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="`Inventario: ${libro.nombre}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.inventario.index')" class="text-sm text-muted-foreground hover:underline">← Volver al inventario</Link>

            <div>
                <h1 class="text-xl font-semibold">{{ libro.nombre }}</h1>
                <p class="font-mono text-xs text-muted-foreground">{{ libro.isbn }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Stock actual</p>
                    <p class="mt-1 text-2xl font-semibold" :class="libro.stock <= libro.stock_minimo ? 'text-amber-600' : ''">{{ libro.stock }}</p>
                    <p class="text-xs text-muted-foreground">Mínimo: {{ libro.stock_minimo }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Costo promedio</p>
                    <p class="mt-1 text-2xl font-semibold">{{ dinero(libro.costo_promedio) }}</p>
                    <p class="text-xs text-muted-foreground">Valor: {{ dinero(libro.valor) }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Total de entradas</p>
                    <p class="mt-1 text-2xl font-semibold text-green-600">+{{ libro.entradas }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Total de salidas</p>
                    <p class="mt-1 text-2xl font-semibold text-red-600">−{{ libro.salidas }}</p>
                </div>
            </div>

            <form class="flex flex-col gap-3 rounded-xl border p-4" @submit.prevent="ajustar">
                <div>
                    <h2 class="font-medium">Ajuste de inventario</h2>
                    <p class="text-sm text-muted-foreground">Para corregir diferencias del conteo físico: libros dañados, perdidos o que aparecieron.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-[160px_120px_1fr]">
                    <div class="grid gap-2">
                        <Label for="tipo">Tipo</Label>
                        <select id="tipo" v-model="form.tipo" :class="claseSelect">
                            <option value="salida">Salida (−)</option>
                            <option value="entrada">Entrada (+)</option>
                        </select>
                        <InputError :message="form.errors.tipo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cantidad">Cantidad</Label>
                        <Input id="cantidad" v-model.number="form.cantidad" type="number" min="1" />
                        <InputError :message="form.errors.cantidad" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="observacion">Motivo</Label>
                        <Input id="observacion" v-model="form.observacion" maxlength="255" placeholder="Ej.: 2 libros dañados por humedad" />
                        <InputError :message="form.errors.observacion" />
                    </div>
                </div>
                <div>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Registrar ajuste
                    </Button>
                </div>
            </form>

            <h2 class="font-medium">Historial de movimientos (kardex)</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Origen</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">Costo</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="m in movimientos.data" :key="m.id_movimiento">
                            <td class="whitespace-nowrap px-3 py-2">{{ m.fecha }}</td>
                            <td class="px-3 py-2">{{ tipos[m.tipo] ?? m.tipo }}</td>
                            <td class="px-3 py-2">
                                <Link v-if="m.origen.compra" :href="route('admin.compras.show', m.origen.compra)" class="hover:underline">{{ m.origen.texto }}</Link>
                                <span v-else>{{ m.origen.texto }}</span>
                                <p v-if="m.observacion" class="text-xs text-muted-foreground">{{ m.observacion }}</p>
                            </td>
                            <td class="px-3 py-2 text-right font-medium" :class="m.cantidad > 0 ? 'text-green-600' : 'text-red-600'">
                                {{ m.cantidad > 0 ? '+' : '' }}{{ m.cantidad }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ dinero(m.costo_unitario) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ m.stock_resultante }}</td>
                        </tr>
                        <tr v-if="!movimientos.data.length">
                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">Este libro todavía no tiene movimientos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="movimientos" />
        </div>
    </AppLayout>
</template>
