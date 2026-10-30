<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { estadoConfirmar } from '@/composables/useConfirmar';
import { AlertTriangle, HelpCircle } from 'lucide-vue-next';

// Si se cierra con la X, con Esc o haciendo clic afuera, cuenta como "Cancelar"
function alCambiar(abierto: boolean) {
    if (!abierto) estadoConfirmar.responder(false);
}
</script>

<template>
    <Dialog :open="estadoConfirmar.abierto" @update:open="alCambiar">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex-row items-start gap-4 space-y-0 text-left">
                <span :class="['rounded-full p-2', estadoConfirmar.peligro ? 'bg-red-100 text-red-600 dark:bg-red-950' : 'bg-muted text-foreground']">
                    <AlertTriangle v-if="estadoConfirmar.peligro" class="h-5 w-5" />
                    <HelpCircle v-else class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <DialogTitle>{{ estadoConfirmar.titulo }}</DialogTitle>
                    <DialogDescription>{{ estadoConfirmar.mensaje }}</DialogDescription>
                </div>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="estadoConfirmar.responder(false)">Cancelar</Button>
                <Button :variant="estadoConfirmar.peligro ? 'destructive' : 'default'" @click="estadoConfirmar.responder(true)">
                    {{ estadoConfirmar.boton }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>