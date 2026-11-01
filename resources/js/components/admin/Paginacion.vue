<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Paginado } from '@/types/tienda';
import { Link } from '@inertiajs/vue3';

defineProps<{ datos: Paginado<unknown> }>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
        <span v-if="datos.total">{{ datos.from }}–{{ datos.to }} de {{ datos.total }}</span>
        <span v-else>Sin resultados</span>

        <div v-if="datos.last_page > 1" class="flex items-center gap-2">
            <Button :disabled="!datos.prev_page_url" variant="outline" size="sm" as-child>
                <Link v-if="datos.prev_page_url" :href="datos.prev_page_url" preserve-scroll preserve-state>Anterior</Link>
                <span v-else>Anterior</span>
            </Button>
            <span>Página {{ datos.current_page }} de {{ datos.last_page }}</span>
            <Button :disabled="!datos.next_page_url" variant="outline" size="sm" as-child>
                <Link v-if="datos.next_page_url" :href="datos.next_page_url" preserve-scroll preserve-state>Siguiente</Link>
                <span v-else>Siguiente</span>
            </Button>
        </div>
    </div>
</template>