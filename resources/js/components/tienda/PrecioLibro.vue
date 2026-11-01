<script setup lang="ts">
import { dinero } from '@/lib/tienda';

defineProps<{ precio: number; precioFinal: number; descuento: number; grande?: boolean }>();
</script>

<template>
    <div class="flex flex-wrap items-baseline gap-2">
        <span :class="['font-semibold', grande ? 'text-2xl' : 'text-base', descuento > 0 ? 'text-pink-700 dark:text-pink-400' : '']">
            {{ dinero(precioFinal) }}
        </span>
        <template v-if="descuento > 0">
            <span class="text-sm text-muted-foreground line-through">{{ dinero(precio) }}</span>
            <span class="rounded bg-pink-100 px-1.5 py-0.5 text-xs font-medium text-pink-800 dark:bg-pink-950 dark:text-pink-300">
                -{{ Math.round((descuento / precio) * 100) }}%
            </span>
        </template>
    </div>
</template>