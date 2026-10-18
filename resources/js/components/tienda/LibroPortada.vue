<script setup lang="ts">
import { computed } from 'vue';

// Portada provisional generada con el título (las fotos reales se agregan después).
const props = defineProps<{ titulo: string; autores?: string; grande?: boolean }>();

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

const fondo = computed(() => {
    let hash = 0;
    for (const letra of props.titulo) hash = (hash * 31 + letra.charCodeAt(0)) >>> 0;
    const [oscuro, claro] = colores[hash % colores.length];
    return `linear-gradient(145deg, ${oscuro}, ${claro})`;
});
</script>

<template>
    <div
        class="flex aspect-[2/3] w-full flex-col justify-between overflow-hidden rounded-md p-3 text-white shadow-sm"
        :style="{ background: fondo }"
    >
        <p :class="['font-semibold leading-tight drop-shadow', grande ? 'line-clamp-5 text-xl' : 'line-clamp-4 text-sm']">{{ titulo }}</p>
        <p v-if="autores" :class="['line-clamp-2 opacity-90 drop-shadow', grande ? 'text-sm' : 'text-xs']">{{ autores }}</p>
    </div>
</template>