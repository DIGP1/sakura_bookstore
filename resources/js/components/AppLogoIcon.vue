<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="/LOGOS_SAKURA_ARBOL.png" alt="Sakura Bookstore" class="h-auto w-auto" />
</template>
