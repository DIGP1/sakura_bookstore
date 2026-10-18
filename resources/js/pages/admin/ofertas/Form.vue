<script setup lang="ts">
import SelectorMultiple from '@/components/admin/SelectorMultiple.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface OfertaEditable {
    id_oferta: number;
    nombre_oferta: string;
    id_tipo_descuento: number;
    valor_descuento: number;
    fecha_inicio: string;
    fecha_fin: string;
    estado: boolean;
    monto_minimo: number | null;
    alcance: 'global' | 'libros' | 'categorias';
    productos: number[];
    categorias: number[];
}

const props = defineProps<{
    oferta: OfertaEditable | null;
    tipos: Opcion[];
    libros: Opcion[];
    categorias: Opcion[];
}>();

const editando = props.oferta !== null;
const hoy = new Date().toISOString().slice(0, 10);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Ofertas', href: '/admin/ofertas' },
    { title: editando ? 'Editar' : 'Nueva', href: '#' },
];

const form = useForm({
    nombre_oferta: props.oferta?.nombre_oferta ?? '',
    id_tipo_descuento: (props.oferta?.id_tipo_descuento ?? props.tipos.find((t) => t.nombre === 'PORCENTAJE')?.id ?? '') as number | '',
    valor_descuento: (props.oferta?.valor_descuento ?? '') as number | '',
    fecha_inicio: props.oferta?.fecha_inicio ?? hoy,
    fecha_fin: props.oferta?.fecha_fin ?? '',
    estado: props.oferta?.estado ?? true,
    alcance: props.oferta?.alcance ?? ('libros' as 'global' | 'libros' | 'categorias'),
    monto_minimo: (props.oferta?.monto_minimo ?? '') as number | '',
    productos: props.oferta?.productos ?? ([] as number[]),
    categorias: props.oferta?.categorias ?? ([] as number[]),
});

const esPorcentaje = computed(() => props.tipos.find((t) => t.id === form.id_tipo_descuento)?.nombre === 'PORCENTAJE');

function guardar() {
    if (props.oferta) {
        form.put(route('admin.ofertas.update', props.oferta.id_oferta), { preserveScroll: true });
    } else {
        form.post(route('admin.ofertas.store'), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="editando ? 'Editar oferta' : 'Nueva oferta'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4" @submit.prevent="guardar">
            <h1 class="text-xl font-semibold">{{ editando ? 'Editar oferta' : 'Nueva oferta' }}</h1>

            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <div class="grid gap-2">
                    <Label for="nombre_oferta">Nombre</Label>
                    <Input id="nombre_oferta" v-model="form.nombre_oferta" maxlength="100" placeholder="Ej.: Feria de novela" />
                    <InputError :message="form.errors.nombre_oferta" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="id_tipo_descuento">Tipo de descuento</Label>
                        <select id="id_tipo_descuento" v-model="form.id_tipo_descuento" :class="claseSelect">
                            <option v-for="tipo in tipos" :key="tipo.id" :value="tipo.id">
                                {{ tipo.nombre === 'PORCENTAJE' ? 'Porcentaje (%)' : 'Monto fijo ($)' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.id_tipo_descuento" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="valor_descuento">Descuento {{ esPorcentaje ? '(%)' : '(US$)' }}</Label>
                        <Input id="valor_descuento" v-model.number="form.valor_descuento" type="number" min="0.01" :max="esPorcentaje ? 100 : undefined" step="0.01" />
                        <InputError :message="form.errors.valor_descuento" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="fecha_inicio">Desde</Label>
                        <Input id="fecha_inicio" v-model="form.fecha_inicio" type="date" />
                        <InputError :message="form.errors.fecha_inicio" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fecha_fin">Hasta</Label>
                        <Input id="fecha_fin" v-model="form.fecha_fin" type="date" :min="form.fecha_inicio" />
                        <InputError :message="form.errors.fecha_fin" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.estado" type="checkbox" class="h-4 w-4" />
                    Activa (la tienda la aplica dentro de sus fechas)
                </label>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <h2 class="font-medium">¿A qué se aplica?</h2>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input v-model="form.alcance" type="radio" value="libros" /> Libros específicos</label>
                    <label class="flex items-center gap-2"><input v-model="form.alcance" type="radio" value="categorias" /> Categorías completas</label>
                    <label class="flex items-center gap-2"><input v-model="form.alcance" type="radio" value="global" /> Toda la compra</label>
                </div>

                <div v-if="form.alcance === 'libros'" class="grid gap-2">
                    <SelectorMultiple v-model="form.productos" :opciones="libros" placeholder="Buscar libro..." />
                    <InputError :message="form.errors.productos" />
                </div>

                <div v-if="form.alcance === 'categorias'" class="grid gap-2">
                    <SelectorMultiple v-model="form.categorias" :opciones="categorias" placeholder="Buscar categoría..." />
                    <InputError :message="form.errors.categorias" />
                </div>

                <div v-if="form.alcance === 'global'" class="grid gap-2 sm:max-w-xs">
                    <Label for="monto_minimo">Compra mínima (US$, opcional)</Label>
                    <Input id="monto_minimo" v-model.number="form.monto_minimo" type="number" min="0" step="0.01" placeholder="Ej.: 60" />
                    <p class="text-xs text-muted-foreground">El descuento se aplica al total si la compra llega a este monto.</p>
                    <InputError :message="form.errors.monto_minimo" />
                </div>
            </section>

            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ editando ? 'Guardar cambios' : 'Crear oferta' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.ofertas.index')">Cancelar</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>