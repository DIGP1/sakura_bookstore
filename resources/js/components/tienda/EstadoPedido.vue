<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ estado: string | null }>();

const estilos: Record<string, { texto: string; clase: string }> = {
    COMPLETADA: { texto: 'Completada', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    ANULADA: { texto: 'Anulada', clase: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' },
    PENDIENTE: { texto: 'Pendiente', clase: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
    EN_CAMINO: { texto: 'En camino', clase: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' },
    ENTREGADO: { texto: 'Entregado', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    FALLIDO: { texto: 'Fallido', clase: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' },
    DEVUELTO: { texto: 'Devuelto', clase: 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-300' },
    SOLICITADA: { texto: 'En revisión', clase: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
    APROBADA: { texto: 'Aprobada', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    RECHAZADA: { texto: 'Rechazada', clase: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' },
    RECIBIDA: { texto: 'Recibida', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    VIGENTE: { texto: 'Vigente', clase: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    PROGRAMADA: { texto: 'Programada', clase: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' },
    VENCIDA: { texto: 'Vencida', clase: 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-300' },
    INACTIVA: { texto: 'Inactiva', clase: 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-300' },
};

const info = computed(
    () => estilos[props.estado ?? ''] ?? { texto: props.estado ?? '—', clase: 'bg-muted text-muted-foreground' },
);
</script>

<template>
    <span :class="['inline-flex rounded px-2 py-0.5 text-xs font-medium', info.clase]">{{ info.texto }}</span>
</template>