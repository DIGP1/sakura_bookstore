<script setup lang="ts">
import { confirmar } from '@/composables/useConfirmar';
import Paginacion from '@/components/admin/Paginacion.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Campo {
    campo: string;
    etiqueta: string;
    requerido: boolean;
}

type Fila = Record<string, string | number | null> & { id: number; nombre: string; usos: number };

const props = defineProps<{
    tipo: string;
    titulo: string;
    singular: string;
    usosTexto: string;
    campos: Campo[];
    filas: Paginado<Fila>;
    filtros: { q: string };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Inicio', href: '/admin' },
    { title: props.titulo, href: `/admin/${props.tipo}` },
]);

// Formulario con los campos que manda el servidor (nombre, país, teléfono...).
const vacio = () => Object.fromEntries(props.campos.map((c) => [c.campo, ''])) as Record<string, string>;
const form = useForm<Record<string, string>>(vacio());
const editandoId = ref<number | null>(null);

const busqueda = ref(props.filtros.q);

function buscar() {
    router.get(route(`admin.${props.tipo}.index`), busqueda.value ? { q: busqueda.value } : {}, { preserveState: true });
}

function editar(fila: Fila) {
    editandoId.value = fila.id;
    props.campos.forEach((c) => (form[c.campo] = (fila[c.campo] ?? '') as string));
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelar() {
    editandoId.value = null;
    form.defaults(vacio());
    form.reset();
    form.clearErrors();
}

function guardar() {
    const opciones = { preserveScroll: true, onSuccess: () => cancelar() };

    if (editandoId.value) {
        form.put(route(`admin.${props.tipo}.update`, editandoId.value), opciones);
    } else {
        form.post(route(`admin.${props.tipo}.store`), opciones);
    }
}

async function eliminar(fila: Fila) {
    if (!(await confirmar({ titulo: 'Eliminar registro', mensaje: `¿Eliminar "${fila.nombre}"?`, boton: 'Eliminar', peligro: true }))) return;
    router.delete(route(`admin.${props.tipo}.destroy`, fila.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="titulo" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">{{ titulo }}</h1>

            <form class="flex flex-col gap-3 rounded-xl border p-4" @submit.prevent="guardar">
                <h2 class="font-medium">{{ editandoId ? `Editar ${singular}` : `Nuevo ${singular}` }}</h2>

                <div class="grid gap-3" :class="campos.length > 2 ? 'sm:grid-cols-3' : campos.length === 2 ? 'sm:grid-cols-2' : ''">
                    <div v-for="campo in campos" :key="campo.campo" class="grid gap-2">
                        <Label :for="campo.campo">{{ campo.etiqueta }}<span v-if="!campo.requerido" class="text-muted-foreground"> (opcional)</span></Label>
                        <Input :id="campo.campo" v-model="form[campo.campo]" />
                        <InputError :message="form.errors[campo.campo]" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ editandoId ? 'Guardar cambios' : 'Agregar' }}
                    </Button>
                    <Button v-if="editandoId" type="button" variant="outline" @click="cancelar">Cancelar</Button>
                </div>
            </form>

            <form class="flex gap-2" @submit.prevent="buscar">
                <Input v-model="busqueda" type="search" :placeholder="`Buscar ${titulo.toLowerCase()}...`" class="w-full sm:w-72" />
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th v-for="campo in campos" :key="campo.campo" class="px-3 py-2">{{ campo.etiqueta }}</th>
                            <th class="px-3 py-2 text-right">{{ usosTexto }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="fila in filas.data" :key="fila.id" :class="editandoId === fila.id ? 'bg-accent/50' : ''">
                            <td v-for="campo in campos" :key="campo.campo" class="px-3 py-2" :class="campo.campo === 'nombre' ? 'font-medium' : ''">
                                {{ fila[campo.campo] ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ fila.usos }}</td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" :aria-label="`Editar ${fila.nombre}`" @click="editar(fila)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-red-600"
                                        :disabled="fila.usos > 0"
                                        :title="fila.usos > 0 ? `Tiene ${usosTexto} asociados` : 'Eliminar'"
                                        :aria-label="`Eliminar ${fila.nombre}`"
                                        @click="eliminar(fila)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filas.data.length">
                            <td :colspan="campos.length + 2" class="px-3 py-8 text-center text-muted-foreground">No hay registros.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="filas" />
        </div>
    </AppLayout>
</template>