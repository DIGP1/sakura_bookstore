<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, X, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';


// Notificaciones flotantes (esquina superior derecha) con los mensajes flash del servidor
interface Aviso {
    id: number;
    tipo: 'exito' | 'error';
    texto: string;
}

const page = usePage<SharedData>();
const avisos = ref<Aviso[]>([]);
let siguienteId = 0;

function cerrar(id: number) {
    avisos.value = avisos.value.filter((aviso) => aviso.id !== id);
}

function mostrar(tipo: Aviso['tipo'], texto: string) {
    const id = ++siguienteId;
    avisos.value.push({ id, tipo, texto });
    setTimeout(() => cerrar(id), tipo === 'error' ? 7000 : 4000); // se quita solo
}

// Cada vez que el servidor manda un mensaje, aparece una notificación
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.exito) mostrar('exito', flash.exito);
        if (flash?.error) mostrar('error', flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-[60] flex w-full max-w-sm flex-col gap-2">
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-8 opacity-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="translate-x-8 opacity-0"
        >
            <div
                v-for="aviso in avisos"
                :key="aviso.id"
                :class="[
                    'pointer-events-auto flex items-start gap-3 rounded-lg border bg-background p-4 text-sm shadow-lg',
                    aviso.tipo === 'exito' ? 'border-l-4 border-l-green-600' : 'border-l-4 border-l-red-600',
                ]"
            >
                <CheckCircle2 v-if="aviso.tipo === 'exito'" class="mt-0.5 h-5 w-5 shrink-0 text-green-600" />
                <XCircle v-else class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <p class="flex-1">{{ aviso.texto }}</p>
                <button type="button" aria-label="Cerrar" class="text-muted-foreground hover:text-foreground" @click="cerrar(aviso.id)">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>