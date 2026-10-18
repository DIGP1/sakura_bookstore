<script setup lang="ts">
import SelectorMultiple from '@/components/admin/SelectorMultiple.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface LibroEditable {
    id_producto: number;
    isbn: string;
    nombre: string;
    id_editorial: number;
    anio_publicacion: number | null;
    idioma: string;
    formato: string;
    num_paginas: number | null;
    precio_unitario: number;
    stock_minimo: number;
    stock: number;
    costo_promedio: number;
    autores: number[];
    categorias: number[];
}

const props = defineProps<{
    libro: LibroEditable | null;
    editoriales: Opcion[];
    autores: Opcion[];
    categorias: Opcion[];
    formatos: { valor: string; nombre: string }[];
}>();

const editando = props.libro !== null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Libros', href: '/admin/libros' },
    { title: editando ? 'Editar' : 'Nuevo', href: '#' },
];

const form = useForm({
    isbn: props.libro?.isbn ?? '',
    nombre: props.libro?.nombre ?? '',
    id_editorial: (props.libro?.id_editorial ?? '') as number | '',
    anio_publicacion: (props.libro?.anio_publicacion ?? '') as number | '',
    idioma: props.libro?.idioma ?? 'Español',
    formato: props.libro?.formato ?? 'RUSTICA',
    num_paginas: (props.libro?.num_paginas ?? '') as number | '',
    precio_unitario: (props.libro?.precio_unitario ?? '') as number | '',
    stock_minimo: props.libro?.stock_minimo ?? 5,
    autores: props.libro?.autores ?? ([] as number[]),
    categorias: props.libro?.categorias ?? ([] as number[]),
});

function guardar() {
    if (props.libro) {
        form.put(route('admin.libros.update', props.libro.id_producto), { preserveScroll: true });
    } else {
        form.post(route('admin.libros.store'), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="editando ? 'Editar libro' : 'Nuevo libro'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">{{ editando ? `Editar: ${libro?.nombre}` : 'Nuevo libro' }}</h1>

            <div v-if="libro" class="grid gap-3 rounded-xl border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                <p>Stock actual: <strong>{{ libro.stock }}</strong></p>
                <p>Costo promedio: <strong>{{ dinero(libro.costo_promedio) }}</strong></p>
                <p class="text-xs text-muted-foreground sm:col-span-2">
                    El stock y el costo no se editan aquí: cambian solos con las compras, ventas, devoluciones y ajustes del kardex.
                </p>
            </div>

            <form class="flex flex-col gap-4 rounded-xl border p-4" @submit.prevent="guardar">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="nombre">Título</Label>
                        <Input id="nombre" v-model="form.nombre" maxlength="200" />
                        <InputError :message="form.errors.nombre" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="isbn">ISBN</Label>
                        <Input id="isbn" v-model="form.isbn" maxlength="17" placeholder="978..." />
                        <InputError :message="form.errors.isbn" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Autores</Label>
                        <SelectorMultiple v-model="form.autores" :opciones="autores" placeholder="Buscar autor..." />
                        <InputError :message="form.errors.autores" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Categorías</Label>
                        <SelectorMultiple v-model="form.categorias" :opciones="categorias" placeholder="Buscar categoría..." />
                        <InputError :message="form.errors.categorias" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="id_editorial">Editorial</Label>
                        <select id="id_editorial" v-model="form.id_editorial" :class="claseSelect">
                            <option value="" disabled>Elegí una</option>
                            <option v-for="editorial in editoriales" :key="editorial.id" :value="editorial.id">{{ editorial.nombre }}</option>
                        </select>
                        <InputError :message="form.errors.id_editorial" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="formato">Formato</Label>
                        <select id="formato" v-model="form.formato" :class="claseSelect">
                            <option v-for="formato in formatos" :key="formato.valor" :value="formato.valor">{{ formato.nombre }}</option>
                        </select>
                        <InputError :message="form.errors.formato" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="idioma">Idioma</Label>
                        <Input id="idioma" v-model="form.idioma" maxlength="30" />
                        <InputError :message="form.errors.idioma" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="anio_publicacion">Año</Label>
                        <Input id="anio_publicacion" v-model.number="form.anio_publicacion" type="number" min="1450" />
                        <InputError :message="form.errors.anio_publicacion" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="num_paginas">Páginas</Label>
                        <Input id="num_paginas" v-model.number="form.num_paginas" type="number" min="1" />
                        <InputError :message="form.errors.num_paginas" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="precio_unitario">Precio (US$)</Label>
                        <Input id="precio_unitario" v-model.number="form.precio_unitario" type="number" min="0.01" step="0.01" />
                        <InputError :message="form.errors.precio_unitario" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="stock_minimo">Stock mínimo</Label>
                        <Input id="stock_minimo" v-model.number="form.stock_minimo" type="number" min="0" />
                        <InputError :message="form.errors.stock_minimo" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ editando ? 'Guardar cambios' : 'Crear libro' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('admin.libros.index')">Cancelar</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>