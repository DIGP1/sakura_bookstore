<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();
const exito = ref<string | null>(null);
const error = ref<string | null>(null);

watch(
    () => page.props.flash,
    (flash) => {
        exito.value = flash?.exito ?? null;
        error.value = flash?.error ?? null;
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div v-if="exito || error" class="flex flex-col gap-2 px-4 pt-4">
        <div v-if="exito" class="flex items-start justify-between gap-3 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            <span>{{ exito }}</span>
            <button type="button" aria-label="Cerrar" @click="exito = null"><X class="h-4 w-4" /></button>
        </div>
        <div v-if="error" class="flex items-start justify-between gap-3 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            <span>{{ error }}</span>
            <button type="button" aria-label="Cerrar" @click="error = null"><X class="h-4 w-4" /></button>
        </div>
    </div>
</template>