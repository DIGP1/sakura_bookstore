<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { Opcion } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface LineaDevolvible {
    id_detalle_venta: number;
    id_producto: number;
    nombre: string;
    comprada: number;
    devuelta: number;
    disponible: number;
    precio_pagado: number;
}

const props = defineProps<{
    pedido: { id_venta: number; numero: string; fecha: string | null; dias_restantes: number };
    lineas: LineaDevolvible[];
    motivos: Opcion[];
    tiposReembolso: Opcion[];
}>();

const form = useForm({
    id_motivo_devolucion: '' as number | '',
    id_tipo_reembolso: (props.tiposReembolso[0]?.id ?? '') as number | '',
    lineas: props.lineas.map((linea) => ({ id_detalle_venta: linea.id_detalle_venta, cantidad: 0 })),
});

const errores = computed(() => form.errors as Record<string, string | undefined>);

const reembolso = computed(() =>
    props.lineas.reduce((total, linea, i) => total + linea.precio_pagado * (Number(form.lineas[i].cantidad) || 0), 0),
);

function enviar() {
    form.post(route('cliente.devoluciones.store', props.pedido.id_venta), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Devolución del pedido #${pedido.numero}`" />

    <TiendaLayout>
        <Link :href="route('cliente.pedidos.show', pedido.id_venta)" class="text-sm text-muted-foreground hover:underline">← Volver al pedido</Link>
        <h1 class="mt-2 text-2xl font-semibold">Solicitar devolución</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Pedido <span class="font-mono">#{{ pedido.numero }}</span> del {{ pedido.fecha }} · te quedan {{ pedido.dias_restantes }}
            {{ pedido.dias_restantes === 1 ? 'día' : 'días' }} para devolverlo.
        </p>

        <div v-if="errores.devolucion" class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ errores.devolucion }}
        </div>

        <form class="grid gap-8 lg:grid-cols-[1fr_320px]" @submit.prevent="enviar">
            <section class="rounded-lg border p-4">
                <h2 class="mb-1 font-semibold">1. ¿Qué libros querés devolver?</h2>
                <p class="mb-4 text-sm text-muted-foreground">Poné la cantidad de cada libro. Dejá en 0 los que no vas a devolver.</p>

                <ul class="divide-y">
                    <li v-for="(linea, i) in lineas" :key="linea.id_detalle_venta" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-medium">{{ linea.nombre }}</p>
                            <p class="text-xs text-muted-foreground">
                                Compraste {{ linea.comprada }}
                                <template v-if="linea.devuelta"> · ya pediste devolver {{ linea.devuelta }}</template>
                                · pagaste {{ dinero(linea.precio_pagado) }} c/u
                            </p>
                        </div>
                        <div v-if="linea.disponible > 0" class="flex items-center gap-2">
                            <Input
                                v-model.number="form.lineas[i].cantidad"
                                type="number"
                                min="0"
                                :max="linea.disponible"
                                class="w-20"
                                :aria-label="`Cantidad a devolver de ${linea.nombre}`"
                            />
                            <span class="text-xs text-muted-foreground">de {{ linea.disponible }}</span>
                        </div>
                        <span v-else class="text-xs text-muted-foreground">Ya no se puede devolver</span>
                    </li>
                </ul>
                <InputError :message="errores.lineas" class="mt-2" />
            </section>

            <aside class="flex h-fit flex-col gap-4 rounded-lg border p-4">
                <h2 class="font-semibold">2. Motivo y reembolso</h2>

                <div class="grid gap-2">
                    <Label for="id_motivo_devolucion">Motivo</Label>
                    <select id="id_motivo_devolucion" v-model="form.id_motivo_devolucion" :class="claseSelect">
                        <option value="" disabled>Elegí uno</option>
                        <option v-for="motivo in motivos" :key="motivo.id" :value="motivo.id">{{ motivo.nombre }}</option>
                    </select>
                    <InputError :message="form.errors.id_motivo_devolucion" />
                </div>

                <div class="grid gap-2">
                    <Label for="id_tipo_reembolso">¿Cómo querés tu reembolso?</Label>
                    <select id="id_tipo_reembolso" v-model="form.id_tipo_reembolso" :class="claseSelect">
                        <option v-for="tipo in tiposReembolso" :key="tipo.id" :value="tipo.id">{{ tipo.nombre }}</option>
                    </select>
                    <InputError :message="form.errors.id_tipo_reembolso" />
                </div>

                <div class="flex justify-between border-t pt-3 font-semibold">
                    <span>Reembolso estimado</span>
                    <span>{{ dinero(reembolso) }}</span>
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">El reembolso se hace cuando aprobemos la devolución.</p>

                <Button type="submit" :disabled="form.processing || reembolso === 0">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Enviar solicitud
                </Button>
            </aside>
        </form>
    </TiendaLayout>
</template>