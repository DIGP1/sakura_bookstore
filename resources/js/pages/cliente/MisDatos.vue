<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { claseSelect } from '@/lib/tienda';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Lugar {
    id_departamento?: number;
    id_municipio?: number;
    id_distrito?: number;
    nombre: string;
}

const props = defineProps<{
    cliente: {
        nombre: string;
        email: string;
        telefono: string | null;
        direccion: string | null;
        id_departamento: number | null;
        id_municipio: number | null;
        id_distrito: number | null;
    };
    departamentos: Lugar[];
}>();

const form = useForm({
    nombre: props.cliente.nombre,
    telefono: props.cliente.telefono ?? '',
    id_departamento: (props.cliente.id_departamento ?? '') as number | '',
    id_municipio: (props.cliente.id_municipio ?? '') as number | '',
    id_distrito: (props.cliente.id_distrito ?? '') as number | '',
    direccion: props.cliente.direccion ?? '',
});

const municipios = ref<Lugar[]>([]);
const distritos = ref<Lugar[]>([]);

async function obtenerJson<T>(url: string): Promise<T> {
    const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
    return respuesta.ok ? respuesta.json() : ([] as T);
}

async function cargarMunicipios(idDepartamento: number | '') {
    municipios.value = idDepartamento ? await obtenerJson<Lugar[]>(route('ubicaciones.municipios', idDepartamento)) : [];
}

async function cargarDistritos(idMunicipio: number | '') {
    distritos.value = idMunicipio ? await obtenerJson<Lugar[]>(route('ubicaciones.distritos', idMunicipio)) : [];
}

// Se llenan los selects con la ubicación que el cliente ya tiene guardada.
onMounted(async () => {
    await cargarMunicipios(form.id_departamento);
    await cargarDistritos(form.id_municipio);
});

function cambiarDepartamento() {
    form.id_municipio = '';
    form.id_distrito = '';
    distritos.value = [];
    cargarMunicipios(form.id_departamento);
}

function cambiarMunicipio() {
    form.id_distrito = '';
    cargarDistritos(form.id_municipio);
}

function guardar() {
    form.patch(route('cliente.datos.update'), { preserveScroll: true });
}

// Cambio de contraseña: usa la ruta que ya trae Laravel (password.update).
const formClave = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function cambiarClave() {
    formClave.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => formClave.reset(),
        onError: () => formClave.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Mis datos" />

    <TiendaLayout>
        <div class="mx-auto max-w-2xl">
            <h1 class="text-2xl font-semibold">Mis datos</h1>
            <p class="mb-6 text-sm text-muted-foreground">Estos datos se usan para llenar tus envíos.</p>

            <form class="flex flex-col gap-4 rounded-lg border p-4" @submit.prevent="guardar">
                <div class="grid gap-2">
                    <Label for="nombre">Nombre completo</Label>
                    <Input id="nombre" v-model="form.nombre" maxlength="120" required />
                    <InputError :message="form.errors.nombre" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="email">Correo</Label>
                        <Input id="email" :model-value="cliente.email" disabled />
                    </div>
                    <div class="grid gap-2">
                        <Label for="telefono">Teléfono</Label>
                        <Input id="telefono" v-model="form.telefono" maxlength="9" placeholder="7123-4567" />
                        <InputError :message="form.errors.telefono" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="id_departamento">Departamento</Label>
                        <select id="id_departamento" v-model="form.id_departamento" :class="claseSelect" required @change="cambiarDepartamento">
                            <option value="" disabled>Elegí uno</option>
                            <option v-for="d in departamentos" :key="d.id_departamento" :value="d.id_departamento">{{ d.nombre }}</option>
                        </select>
                        <InputError :message="form.errors.id_departamento" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="id_municipio">Municipio</Label>
                        <select id="id_municipio" v-model="form.id_municipio" :class="claseSelect" :disabled="!municipios.length" required @change="cambiarMunicipio">
                            <option value="" disabled>Elegí uno</option>
                            <option v-for="m in municipios" :key="m.id_municipio" :value="m.id_municipio">{{ m.nombre }}</option>
                        </select>
                        <InputError :message="form.errors.id_municipio" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="id_distrito">Distrito</Label>
                        <select id="id_distrito" v-model="form.id_distrito" :class="claseSelect" :disabled="!distritos.length" required>
                            <option value="" disabled>Elegí uno</option>
                            <option v-for="d in distritos" :key="d.id_distrito" :value="d.id_distrito">{{ d.nombre }}</option>
                        </select>
                        <InputError :message="form.errors.id_distrito" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="direccion">Dirección</Label>
                    <Input id="direccion" v-model="form.direccion" maxlength="255" placeholder="Colonia, calle, número de casa, referencia" />
                    <InputError :message="form.errors.direccion" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Guardar cambios
                    </Button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">Guardado.</span>
                </div>
            </form>

            <h2 class="mb-1 mt-10 text-xl font-semibold">Cambiar contraseña</h2>
            <p class="mb-4 text-sm text-muted-foreground">Usá una contraseña larga que no uses en otros sitios.</p>

            <form class="flex flex-col gap-4 rounded-lg border p-4" @submit.prevent="cambiarClave">
                <div class="grid gap-2">
                    <Label for="current_password">Contraseña actual</Label>
                    <Input id="current_password" v-model="formClave.current_password" type="password" autocomplete="current-password" />
                    <InputError :message="formClave.errors.current_password" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">Contraseña nueva</Label>
                        <Input id="password" v-model="formClave.password" type="password" autocomplete="new-password" />
                        <InputError :message="formClave.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">Repetí la contraseña nueva</Label>
                        <Input id="password_confirmation" v-model="formClave.password_confirmation" type="password" autocomplete="new-password" />
                        <InputError :message="formClave.errors.password_confirmation" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="formClave.processing">
                        <LoaderCircle v-if="formClave.processing" class="h-4 w-4 animate-spin" />
                        Cambiar contraseña
                    </Button>
                    <span v-if="formClave.recentlySuccessful" class="text-sm text-muted-foreground">Contraseña cambiada.</span>
                </div>
            </form>
        </div>
    </TiendaLayout>
</template>