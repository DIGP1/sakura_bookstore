<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const usuario = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Inicio" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link :href="route('home')" class="flex items-center gap-2 font-semibold">
                    <BookOpen class="h-5 w-5" />
                    Sakura Bookstore
                </Link>

                <div class="flex items-center gap-3 text-sm">
                    <template v-if="usuario">
                        <span class="hidden text-muted-foreground sm:inline">Hola, {{ usuario.name }}</span>

                        <Button v-if="usuario.rol === 'admin'" as-child variant="outline" size="sm">
                            <Link :href="route('dashboard')">Panel de administración</Link>
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

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-4 py-16">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Libros para cada lector</h1>
            <p class="mt-3 max-w-xl text-muted-foreground">
                Literatura salvadoreña, clásicos, novela, textos escolares y más. Muy pronto vas a poder explorar el catálogo y comprar desde aquí.
            </p>

            <p v-if="usuario?.rol === 'cliente'" class="mt-6 text-sm">
                Tu cuenta de cliente está lista. Cuando abramos la tienda podrás comprar con ella.
            </p>
        </main>

        <footer class="border-t py-6 text-center text-xs text-muted-foreground">
            Sakura Bookstore · Proyecto académico, UES FMO
        </footer>
    </div>
</template>