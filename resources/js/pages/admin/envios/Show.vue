<script setup lang="ts">
import { confirmar } from '@/composables/useConfirmar';
import InputError from '@/components/InputError.vue';
import EstadoPedido from '@/components/tienda/EstadoPedido.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { BreadcrumbItem } from '@/types';
import type { Opcion } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    envio: {
        id_envio: number;
        id_venta: number;
        numero: string;
        estado: string | null;
        tipo: string | null;
        id_proveedor_envio: number;
        proveedor: string | null;
        cliente: string | null;
        receptor: string;
        telefono: string;
        direccion: string;
        ubicacion: string;
        costo: number;
        fecha_estimada: string | null;
        fecha_de_envio: string | null;
        fecha_entrega: string | null;
        libros: { nombre: string; cantidad: number }[];
        historial: { estado: string | null; fecha: string | null; observacion: string | null }[];
        venta_anulada: boolean;
        siguientes: string[];
        puede_cambiar_proveedor: boolean;
    };
    proveedores: Opcion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/admin' },
    { title: 'Envíos', href: '/admin/envios' },
    { title: `#${props.envio.numero}`, href: '#' },
];

const acciones: Record<string, { texto: string; clase: string }> = {
    EN_CAMINO: { texto: 'Marcar en camino', clase: '' },
    ENTREGADO: { texto: 'Marcar entregado', clase: 'bg-green-600 text-white hover:bg-green-700' },
    FALLIDO: { texto: 'Marcar fallido', clase: 'text-red-600' },
    DEVUELTO: { texto: 'Marcar devuelto', clase: 'text-red-600' },
};

const form = useForm({
    estado: '',
    observacion: '',
    id_proveedor_envio: props.envio.id_proveedor_envio as number | '',
});

async function cambiar(estado: string) {
    if (!(await confirmar({ titulo: 'Actualizar envío', mensaje: `¿Cambiar el envío a "${acciones[estado]?.texto.replace('Marcar ', '')}"?`, boton: 'Cambiar' }))) return;
    form.estado = estado;
    form.post(route('admin.envios.estado', props.envio.id_envio), {
        preserveScroll: true,
        onSuccess: () => form.reset('observacion', 'estado'),
    });
}
</script>

<template>
    <Head :title="`Envío #${envio.numero}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Link :href="route('admin.envios.index')" class="text-sm text-muted-foreground hover:underline">← Volver a envíos</Link>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-xl font-semibold">Envío del pedido #{{ envio.numero }}</h1>
                <EstadoPedido :estado="envio.estado" />
                <Link :href="route('admin.ventas.show', envio.id_venta)" class="text-sm text-muted-foreground hover:underline">Ver venta</Link>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_340px]">
                <div class="flex flex-col gap-4">
                    <section class="grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground">Entregar a</p>
                            <p class="font-medium">{{ envio.receptor }} · {{ envio.telefono }}</p>
                            <p>{{ envio.direccion }}</p>
                            <p class="text-muted-foreground">{{ envio.ubicacion }}</p>
                        </div>
                        <div class="space-y-1">
                            <p><span class="text-muted-foreground">Cliente:</span> {{ envio.cliente }}</p>
                            <p><span class="text-muted-foreground">Tipo:</span> {{ envio.tipo }} · {{ dinero(envio.costo) }}</p>
                            <p><span class="text-muted-foreground">Proveedor:</span> {{ envio.proveedor }}</p>
                            <p><span class="text-muted-foreground">Estimado:</span> {{ envio.fecha_estimada }}</p>
                            <p v-if="envio.fecha_de_envio"><span class="text-muted-foreground">Salió:</span> {{ envio.fecha_de_envio }}</p>
                            <p v-if="envio.fecha_entrega"><span class="text-muted-foreground">Entregado:</span> {{ envio.fecha_entrega }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl border p-4 text-sm">
                        <h2 class="mb-2 font-medium">Libros del paquete</h2>
                        <ul>
                            <li v-for="(libro, i) in envio.libros" :key="i">{{ libro.cantidad }} × {{ libro.nombre }}</li>
                        </ul>
                    </section>

                    <section v-if="envio.siguientes.length" class="flex flex-col gap-3 rounded-xl border p-4">
                        <h2 class="font-medium">Actualizar envío</h2>

                        <div v-if="envio.puede_cambiar_proveedor" class="grid gap-2 sm:max-w-sm">
                            <Label for="id_proveedor_envio">Proveedor que lo lleva</Label>
                            <select id="id_proveedor_envio" v-model="form.id_proveedor_envio" :class="claseSelect">
                                <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                            </select>
                            <InputError :message="form.errors.id_proveedor_envio" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="observacion">Nota (opcional)</Label>
                            <Input id="observacion" v-model="form.observacion" maxlength="255" placeholder="Ej.: Cliente no estaba en casa" />
                            <InputError :message="form.errors.observacion" />
                        </div>
                        <InputError :message="form.errors.estado" />

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="estado in envio.siguientes"
                                :key="estado"
                                :variant="estado === 'ENTREGADO' || estado === 'EN_CAMINO' ? 'default' : 'outline'"
                                :class="acciones[estado]?.clase"
                                :disabled="form.processing"
                                @click="cambiar(estado)"
                            >
                                {{ acciones[estado]?.texto ?? estado }}
                            </Button>
                        </div>
                    </section>

                    <p v-else-if="envio.venta_anulada" class="rounded-xl border p-4 text-sm text-muted-foreground">
                        La venta de este envío fue anulada; ya no se puede mover.
                    </p>
                    <p v-else class="rounded-xl border p-4 text-sm text-muted-foreground">Este envío ya terminó su recorrido.</p>
                </div>

                <section class="h-fit rounded-xl border p-4 text-sm">
                    <h2 class="mb-3 font-medium">Seguimiento</h2>
                    <ol class="relative ml-2 border-l">
                        <li v-for="(h, i) in envio.historial" :key="i" class="mb-4 ml-4 last:mb-0">
                            <span
                                :class="[
                                    'absolute -left-1.5 mt-1 h-3 w-3 rounded-full border-2 border-background',
                                    i === envio.historial.length - 1 ? 'bg-primary' : 'bg-muted-foreground/40',
                                ]"
                            />
                            <EstadoPedido :estado="h.estado" />
                            <p class="mt-1 text-xs text-muted-foreground">{{ h.fecha }}</p>
                            <p v-if="h.observacion" class="text-xs">{{ h.observacion }}</p>
                        </li>
                    </ol>
                </section>
            </div>
        </div>
    </AppLayout>
</template>