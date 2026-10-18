<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, Search, ShoppingCart, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage<SharedData>();
const usuario = computed(() => page.props.auth.user);
const cantidadCarrito = computed(() => page.props.carrito?.cantidad ?? 0);

// Avisos que manda el servidor (flash) y el error de stock del carrito.
const exito = ref<string | null>(null);
const error = ref<string | null>(null);

watch(
    () => [page.props.flash, page.props.errors] as const,
    ([flash, errores]) => {
        exito.value = flash?.exito ?? null;
        error.value = flash?.error ?? (errores as Record<string, string>)?.cantidad ?? null;
    },
    { immediate: true, deep: true },
);

const busqueda = ref(new URLSearchParams(page.url.split('?')[1] ?? '').get('q') ?? '');

function buscar() {
    router.get(route('catalogo.index'), busqueda.value ? { q: busqueda.value } : {});
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="sticky top-0 z-10 border-b bg-background/95 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
                <Link :href="route('home')" class="flex items-center gap-2 font-semibold">
                    <BookOpen class="h-5 w-5" />
                    <span class="hidden sm:inline">Sakura Bookstore</span>
                </Link>

                <Link :href="route('catalogo.index')" class="text-sm text-muted-foreground hover:text-foreground">Catálogo</Link>

                <form class="order-last flex w-full items-center gap-2 sm:order-none sm:w-auto sm:flex-1" @submit.prevent="buscar">
                    <Input v-model="busqueda" type="search" placeholder="Buscar por título, autor o ISBN" class="h-9" />
                    <Button type="submit" size="sm" variant="outline" aria-label="Buscar">
                        <Search class="h-4 w-4" />
                    </Button>
                </form>

                <div class="ml-auto flex items-center gap-2 text-sm sm:ml-0">
                    <Link :href="route('carrito.index')" class="relative rounded-md p-2 hover:bg-accent" aria-label="Carrito">
                        <ShoppingCart class="h-5 w-5" />
                        <span
                            v-if="cantidadCarrito > 0"
                            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-medium text-primary-foreground"
                        >
                            {{ cantidadCarrito }}
                        </span>
                    </Link>

                    <template v-if="usuario">
                        <span class="hidden text-muted-foreground lg:inline">Hola, {{ usuario.name }}</span>
                        <Button v-if="usuario.rol === 'admin'" as-child variant="outline" size="sm">
                            <Link :href="route('dashboard')">Panel</Link>
                        </Button>
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="route('logout')" method="post" as="button">Cerrar sesión</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="route('login')">Iniciar sesión</Link>
                        </Button>
                        <Button as-child size="sm">
                            <Link :href="route('register')">Crear cuenta</Link>
                        </Button>
                    </template>
                </div>
            </nav>
        </header>

        <div class="mx-auto w-full max-w-6xl px-4">
            <div v-if="exito" class="mt-4 flex items-start justify-between gap-3 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                <span>{{ exito }}</span>
                <button type="button" aria-label="Cerrar" @click="exito = null"><X class="h-4 w-4" /></button>
            </div>
            <div v-if="error" class="mt-4 flex items-start justify-between gap-3 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                <span>{{ error }}</span>
                <button type="button" aria-label="Cerrar" @click="error = null"><X class="h-4 w-4" /></button>
            </div>
        </div>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="border-t py-6 text-center text-xs text-muted-foreground">Sakura Bookstore · Proyecto académico, UES FMO</footer>
    </div>
</template>