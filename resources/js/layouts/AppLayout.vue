<script setup lang="ts">
import Avisos from '@/components/admin/Avisos.vue';
import Confirmacion from '@/components/admin/Confirmacion.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Avisos />
            <Confirmacion />
        <slot />
    </AppLayout>
</template>