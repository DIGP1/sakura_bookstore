<script setup lang="ts">
import type { Disponibilidad } from '@/types/tienda';
import { computed } from 'vue';

const props = defineProps<{ estado: Disponibilidad; stock: number }>();

const info = computed(() => {
    switch (props.estado) {
        case 'agotado':
            return { texto: 'Agotado', clase: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' };
        case 'pocos':
            return {
                texto: props.stock === 1 ? '¡Queda 1!' : `Quedan pocos (${props.stock})`,
                clase: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            };
        default:
            return { texto: 'Disponible', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' };
    }
});
</script>

<template>
    <span :class="['inline-flex rounded px-2 py-0.5 text-xs font-medium', info.clase]">{{ info.texto }}</span>
</template>