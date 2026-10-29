<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    titulo: string;
    autores?: string;
    grande?: boolean;
    cargarInmediatamente?: boolean;
    src?: string;
    alt?: string;
}>();

const colores = [
    ['#be185d', '#f9a8d4'],
    ['#1d4ed8', '#93c5fd'],
    ['#047857', '#6ee7b7'],
    ['#b45309', '#fcd34d'],
    ['#6d28d9', '#c4b5fd'],
    ['#0f766e', '#5eead4'],
    ['#b91c1c', '#fca5a5'],
    ['#334155', '#cbd5e1'],
];

const imagenFallida = ref(false);

const fondo = computed(() => {
    let hash = 0;
    for (const letra of props.titulo) hash = (hash * 31 + letra.charCodeAt(0)) >>> 0;
    const [oscuro, claro] = colores[hash % colores.length];
    return `linear-gradient(145deg, ${oscuro}, ${claro})`;
});

const mostrarImagen = computed(() => Boolean(props.src) && !imagenFallida.value);
</script>

<template>
    <div
        class="relative flex aspect-[2/3] w-full flex-col justify-between overflow-hidden rounded-md p-3 text-white shadow-sm"
        :style="{ background: fondo }"
    >
        <img
            v-if="mostrarImagen"
            :src="src"
            :alt="alt ?? `Portada de ${titulo}`"
            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-300"
            :loading="cargarInmediatamente || grande ? 'eager' : 'lazy'"
            :fetchpriority="cargarInmediatamente || grande ? 'high' : 'auto'"
            decoding="async"
            @error="imagenFallida = true"
        />
        <template v-else>
            <p :class="['font-semibold leading-tight drop-shadow', grande ? 'line-clamp-5 text-xl' : 'line-clamp-4 text-sm']">{{ titulo }}</p>
            <p v-if="autores" :class="['line-clamp-2 opacity-90 drop-shadow', grande ? 'text-sm' : 'text-xs']">{{ autores }}</p>
        </template>
    </div>
</template>
