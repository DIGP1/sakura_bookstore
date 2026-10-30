<script setup lang="ts">
import { confirmar } from '@/composables/useConfirmar';
import Paginacion from '@/components/admin/Paginacion.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Paginado } from '@/types/tienda';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Power, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface OfertaFila {
    id_oferta: number;
    nombre: string;
    descuento: string;
    alcance: string;
    fechas: string;
    situacion: string;
    activa: boolean;
    usos: number;
}

const props = defineProps<{ ofertas: Paginado<OfertaFila>; filtros: { q: string } }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Ofertas', href: '/admin/ofertas' },
];

const busqueda = ref(props.filtros.q);

function buscar() {
    router.get(route('admin.ofertas.index'), busqueda.value ? { q: busqueda.value } : {}, { preserveState: true });
}

function alternar(oferta: OfertaFila) {
    router.post(route('admin.ofertas.toggle', oferta.id_oferta), {}, { preserveScroll: true });
}

async function eliminar(oferta: OfertaFila) {
    if (!(await confirmar({ titulo: 'Eliminar oferta', mensaje: `¿Eliminar la oferta "${oferta.nombre}"?`, boton: 'Eliminar', peligro: true }))) return;
    router.delete(route('admin.ofertas.destroy', oferta.id_oferta), { preserveScroll: true });
}
</script>

<template>
    <Head title="Ofertas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Ofertas</h1>
                <Button as-child>
                    <Link :href="route('admin.ofertas.create')"><Plus class="h-4 w-4" /> Nueva oferta</Link>
                </Button>
            </div>

            <form class="flex gap-2" @submit.prevent="buscar">
                <Input v-model="busqueda" type="search" placeholder="Buscar oferta..." class="w-full sm:w-72" />
                <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Oferta</th>
                            <th class="px-3 py-2">Descuento</th>
                            <th class="px-3 py-2">Aplica a</th>
                            <th class="px-3 py-2">Vigencia</th>
                            <th class="px-3 py-2 text-right">Usos</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="oferta in ofertas.data" :key="oferta.id_oferta">
                            <td class="px-3 py-2 font-medium">{{ oferta.nombre }}</td>
                            <td class="px-3 py-2">{{ oferta.descuento }}</td>
                            <td class="px-3 py-2">{{ oferta.alcance }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ oferta.fechas }}</td>
                            <td class="px-3 py-2 text-right">{{ oferta.usos }}</td>
                            <td class="px-3 py-2"><EstadoPedido :estado="oferta.situacion" /></td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" :title="oferta.activa ? 'Desactivar' : 'Activar'" @click="alternar(oferta)">
                                        <Power :class="['h-4 w-4', oferta.activa ? 'text-green-600' : 'text-muted-foreground']" />
                                    </Button>
                                    <Button variant="ghost" size="icon" as-child :aria-label="`Editar ${oferta.nombre}`">
                                        <Link :href="route('admin.ofertas.edit', oferta.id_oferta)"><Pencil class="h-4 w-4" /></Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-red-600"
                                        :disabled="oferta.usos > 0"
                                        :title="oferta.usos > 0 ? 'Ya se usó en ventas' : 'Eliminar'"
                                        @click="eliminar(oferta)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!ofertas.data.length">
                            <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">No hay ofertas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginacion :datos="ofertas" />
        </div>
    </AppLayout>
</template>