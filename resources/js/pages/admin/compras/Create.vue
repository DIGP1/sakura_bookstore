<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Libro {
    id: number;
    nombre: string;
    isbn: string;
    stock: number;
    costo: number;
}

interface Linea {
    id_producto: number | '';
    cantidad: number;
    costo_unitario: number | '';
}

const props = defineProps<{
    proveedores: Opcion[];
    libros: Libro[];
    precios: Record<string, Record<string, number>>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Compras', href: '/admin/compras' },
    { title: 'Nueva', href: '#' },
];

const form = useForm({
    id_proveedor: '' as number | '',
    numero_factura: '',
    lineas: [{ id_producto: '', cantidad: 1, costo_unitario: '' }] as Linea[],
});

const errores = computed(() => form.errors as Record<string, string | undefined>);

const total = computed(() =>
    form.lineas.reduce((suma, linea) => suma + (Number(linea.cantidad) || 0) * (Number(linea.costo_unitario) || 0), 0),
);

function agregarLinea() {
    form.lineas.push({ id_producto: '', cantidad: 1, costo_unitario: '' });
}

function quitarLinea(i: number) {
    form.lineas.splice(i, 1);
}

// Al elegir un libro se prellena el costo: primero el precio del proveedor, si no, el costo promedio.
function elegirLibro(linea: Linea) {
    if (!linea.id_producto) return;
    const delProveedor = form.id_proveedor ? props.precios[form.id_proveedor]?.[linea.id_producto] : undefined;
    const libro = props.libros.find((l) => l.id === linea.id_producto);
    const costo = delProveedor ?? libro?.costo ?? 0;
    linea.costo_unitario = costo > 0 ? costo : '';
}

function stockDe(id: number | '') {
    return props.libros.find((l) => l.id === id)?.stock;
}

function guardar() {
    form.post(route('admin.compras.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Nueva compra" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4" @submit.prevent="guardar">
            <h1 class="text-xl font-semibold">Nueva compra</h1>

            <section class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="id_proveedor">Proveedor</Label>
                    <select id="id_proveedor" v-model="form.id_proveedor" :class="claseSelect">
                        <option value="" disabled>Elegí uno</option>
                        <option v-for="proveedor in proveedores" :key="proveedor.id" :value="proveedor.id">{{ proveedor.nombre }}</option>
                    </select>
                    <InputError :message="form.errors.id_proveedor" />
                </div>
                <div class="grid gap-2">
                    <Label for="numero_factura">N.º de factura</Label>
                    <Input id="numero_factura" v-model="form.numero_factura" maxlength="30" />
                    <InputError :message="form.errors.numero_factura" />
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">Libros</h2>
                    <Button type="button" variant="outline" size="sm" @click="agregarLinea"><Plus class="h-4 w-4" /> Agregar libro</Button>
                </div>

                <div v-for="(linea, i) in form.lineas" :key="i" class="grid items-start gap-2 border-b pb-3 sm:grid-cols-[1fr_100px_130px_110px_40px]">
                    <div class="grid gap-1">
                        <select v-model="linea.id_producto" :class="claseSelect" :aria-label="`Libro de la línea ${i + 1}`" @change="elegirLibro(linea)">
                            <option value="" disabled>Elegí un libro</option>
                            <option v-for="libro in libros" :key="libro.id" :value="libro.id">{{ libro.nombre }} ({{ libro.isbn }})</option>
                        </select>
                        <p v-if="linea.id_producto" class="text-xs text-muted-foreground">Stock actual: {{ stockDe(linea.id_producto) }}</p>
                        <InputError :message="errores[`lineas.${i}.id_producto`]" />
                    </div>
                    <div class="grid gap-1">
                        <Input v-model.number="linea.cantidad" type="number" min="1" :aria-label="`Cantidad de la línea ${i + 1}`" />
                        <InputError :message="errores[`lineas.${i}.cantidad`]" />
                    </div>
                    <div class="grid gap-1">
                        <Input v-model.number="linea.costo_unitario" type="number" min="0.01" step="0.01" placeholder="Costo c/u" :aria-label="`Costo de la línea ${i + 1}`" />
                        <InputError :message="errores[`lineas.${i}.costo_unitario`]" />
                    </div>
                    <p class="pt-2 text-right text-sm font-medium">
                        {{ dinero((Number(linea.cantidad) || 0) * (Number(linea.costo_unitario) || 0)) }}
                    </p>
                    <Button type="button" variant="ghost" size="icon" class="text-red-600" :disabled="form.lineas.length === 1" aria-label="Quitar línea" @click="quitarLinea(i)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
                <InputError :message="errores.lineas" />

                <div class="flex justify-end text-lg font-semibold">Total: {{ dinero(total) }}</div>
            </section>

            <p class="text-sm text-muted-foreground">
                La compra se guarda como <strong>pendiente</strong>. El stock sube cuando la marqués como recibida.
            </p>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Registrar compra
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.compras.index')">Cancelar</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>