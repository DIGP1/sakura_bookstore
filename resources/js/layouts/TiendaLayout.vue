<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, House, LogIn, LogOut, Search, ShoppingCart, AlignJustify, UserRoundCheck, UserRound, UserRoundPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage<SharedData>();
const usuario = computed(() => page.props.auth.user);
const cantidadCarrito = computed(() => page.props.carrito?.cantidad ?? 0);
const categorias = computed(() => page.props.categorias ?? []);

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
        <header class="sticky top-0 z-20 bg-[#5a9fa0] text-white shadow-sm">
            <nav class="mx-auto flex max-w-7xl items-center gap-5 px-5 py-3 lg:gap-10">
                <Link :href="route('home')" class="shrink-0" aria-label="Sakura Bookstore">
                    <img src="/logo-sakura.png" alt="Sakura Bookstore" class="h-20 w-auto " />
                </Link>

                <div class="hidden flex-1 items-center justify-center gap-8 text-sm font-semibold lg:flex">
                    <Link :href="route('home')" class="flex items-center gap-2 transition-opacity hover:opacity-75">
                        <House class="h-5 w-5" />
                        <span>Tienda</span>
                    </Link>
                    <Link :href="route('catalogo.index')" class="flex items-center gap-2 transition-opacity hover:opacity-75">
                        <BookOpen class="h-5 w-5" />
                        <span>Catálogo</span>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger>
                            <button class="flex shrink-0 items-center gap-2 whitespace-nowrap transition-opacity hover:opacity-75">
                                <AlignJustify class="h-5 w-5" />
                                <span>Categorías</span>
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="center" class="z-50 max-h-80 w-56 overflow-y-auto">
                            <DropdownMenuLabel>Categorías disponibles</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-for="categoria in categorias" :key="categoria.id" :as-child="true">
                                <Link :href="route('catalogo.index', { categoria: categoria.id })" class="w-full">
                                    {{ categoria.nombre }}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="!categorias.length" disabled>No hay categorías disponibles</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <form class="ml-auto flex h-10 w-full max-w-[230px] overflow-hidden rounded-sm bg-white text-slate-500 sm:max-w-[270px]" @submit.prevent="buscar">
                    <input
                        v-model="busqueda"
                        type="search"
                        placeholder="Búsqueda"
                        aria-label="Buscar en el catálogo"
                        class="min-w-0 flex-1 border-0 bg-transparent px-4 text-sm outline-none placeholder:text-slate-400 focus:ring-0"
                    />
                    <button type="submit" class="flex w-12 shrink-0 items-center justify-center bg-slate-800 text-white transition-colors hover:bg-slate-700" aria-label="Buscar">
                        <Search class="h-5 w-5" />
                    </button>
                </form>

                <div class="flex shrink-0 items-center gap-4">
                    <Link :href="route('carrito.index')" class="relative transition-opacity hover:opacity-75" aria-label="Carrito">
                        <ShoppingCart class="h-5 w-5" />
                        <span
                            v-if="cantidadCarrito > 0"
                            class="absolute -right-3 -top-3 flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-xs font-bold text-[#5a9fa0]">
                            {{ cantidadCarrito }}
                        </span>
                    </Link>

                    <template v-if="usuario">
                        <DropdownMenu>
                            <DropdownMenuTrigger>
                                <button class="flex items-center gap-1.5 transition-opacity hover:opacity-75" aria-label="Abrir menú de usuario">
                                    <UserRound class="h-5 w-5" />
                                    {{ usuario.name }}
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-64">
                                <DropdownMenuLabel class="font-normal">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-semibold text-foreground">{{ usuario.name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ usuario.email }}</span>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem v-if="usuario.rol === 'cliente'" :as-child="true">
                                    <Link :href="route('cliente.pedidos')" class="w-full">Mis pedidos</Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="usuario.rol === 'cliente'" :as-child="true">
                                    <Link :href="route('cliente.datos')" class="w-full">Mis datos</Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="usuario.rol === 'admin'" :as-child="true">
                                    <Link :href="route('dashboard')" class="w-full">Panel administrativo</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem :as-child="true">
                                    <Link :href="route('logout')" method="post" as="button" class="w-full">
                                        Cerrar sesión
                                        <LogOut class="ml-auto h-4 w-4" />
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="hidden items-center gap-1 text-sm font-semibold transition-opacity hover:opacity-75 sm:flex" aria-label="Iniciar sesión">
                            <UserRoundPlus class="h-5 w-5" />
                            Iniciar sesión
                        </Link>
                        <!-- <Link :href="route('register')" class="hidden items-center gap-1 text-sm font-semibold transition-opacity hover:opacity-75 sm:flex">
                            <UserRoundPlus class="h-5 w-5" />
                            Crear cuenta
                        </Link> -->
                    </template>
                </div>
            </nav>
            <!-- <div class="grid grid-cols-3 items-center gap-2 px-3 pb-3 text-center text-sm font-semibold lg:hidden">
                <Link :href="route('home')" class="flex min-w-0 items-center justify-center gap-1.5 whitespace-nowrap"><House class="h-4 w-4 shrink-0" /> <span>Tienda</span></Link>
                <Link :href="route('catalogo.index')" class="flex min-w-0 items-center justify-center gap-1.5 whitespace-nowrap"><BookOpen class="h-4 w-4 shrink-0" /> <span>Catálogo</span></Link>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button class="flex min-w-0 items-center justify-center gap-1.5 whitespace-nowrap"><Tag class="h-4 w-4 shrink-0" /> <span>Categorías</span></button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="center" class="z-50 max-h-72 w-56 overflow-y-auto">
                        <DropdownMenuLabel>Categorías disponibles</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem v-for="categoria in categorias" :key="categoria.id" :as-child="true">
                            <Link :href="route('catalogo.index', { categoria: categoria.id })" class="w-full">{{ categoria.nombre }}</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div> -->
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