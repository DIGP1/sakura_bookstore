<script setup lang="ts">
import { Input } from '@/components/ui/input';
import type { Opcion } from '@/types/tienda';
import { X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ opciones: Opcion[]; placeholder?: string }>();
const seleccion = defineModel<number[]>({ required: true });

const busqueda = ref('');

const filtradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();
    return texto ? props.opciones.filter((o) => o.nombre.toLowerCase().includes(texto)) : props.opciones;
});

const elegidas = computed(() => props.opciones.filter((o) => seleccion.value.includes(o.id)));

function alternar(id: number) {
    seleccion.value = seleccion.value.includes(id) ? seleccion.value.filter((x) => x !== id) : [...seleccion.value, id];
}
</script>

<template>
    <div class="flex flex-col gap-2 rounded-md border p-2">
        <div v-if="elegidas.length" class="flex flex-wrap gap-1">
            <span v-for="opcion in elegidas" :key="opcion.id" class="inline-flex items-center gap-1 rounded bg-accent px-2 py-0.5 text-xs">
                {{ opcion.nombre }}
                <button type="button" :aria-label="`Quitar ${opcion.nombre}`" @click="alternar(opcion.id)"><X class="h-3 w-3" /></button>
            </span>
        </div>

        <Input v-model="busqueda" type="search" :placeholder="placeholder ?? 'Buscar...'" class="h-8" />

        <ul class="max-h-44 overflow-y-auto text-sm">
            <li v-for="opcion in filtradas" :key="opcion.id">
                <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-accent">
                    <input type="checkbox" class="h-4 w-4" :checked="seleccion.includes(opcion.id)" @change="alternar(opcion.id)" />
                    {{ opcion.nombre }}
                </label>
            </li>
            <li v-if="!filtradas.length" class="px-1 py-1 text-muted-foreground">Sin coincidencias</li>
        </ul>
    </div>
</template>