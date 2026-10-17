<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Departamento {
    id_departamento: number;
    nombre: string;
}

interface Municipio {
    id_municipio: number;
    nombre: string;
}

interface Distrito {
    id_distrito: number;
    nombre: string;
}

defineProps<{
    departamentos: Departamento[];
}>();

const form = useForm({
    name: '',
    email: '',
    telefono: '',
    id_departamento: '' as number | '',
    id_municipio: '' as number | '',
    id_distrito: '' as number | '',
    direccion: '',
    password: '',
    password_confirmation: '',
});

const municipios = ref<Municipio[]>([]);
const distritos = ref<Distrito[]>([]);
const cargandoMunicipios = ref(false);
const cargandoDistritos = ref(false);

async function obtenerJson<T>(url: string): Promise<T> {
    const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
    return respuesta.json();
}

// Al cambiar el departamento se reinician municipio y distrito y se cargan los municipios.
watch(
    () => form.id_departamento,
    async (idDepartamento) => {
        form.id_municipio = '';
        form.id_distrito = '';
        municipios.value = [];
        distritos.value = [];

        if (!idDepartamento) return;

        cargandoMunicipios.value = true;
        municipios.value = await obtenerJson<Municipio[]>(route('ubicaciones.municipios', idDepartamento));
        cargandoMunicipios.value = false;
    },
);

// Al cambiar el municipio se reinicia el distrito y se cargan los distritos.
watch(
    () => form.id_municipio,
    async (idMunicipio) => {
        form.id_distrito = '';
        distritos.value = [];

        if (!idMunicipio) return;

        cargandoDistritos.value = true;
        distritos.value = await obtenerJson<Distrito[]>(route('ubicaciones.distritos', idMunicipio));
        cargandoDistritos.value = false;
    },
);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const claseSelect =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';
</script>

<template>
    <AuthBase title="Crear una cuenta" description="Ingresa tus datos para registrarte y comprar en Sakura Bookstore">
        <Head title="Registro" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nombre completo</Label>
                    <Input id="name" type="text" required autofocus autocomplete="name" v-model="form.name" placeholder="Ej. Ana Martínez" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" type="email" required autocomplete="email" v-model="form.email" placeholder="correo@ejemplo.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="telefono">Teléfono</Label>
                    <Input id="telefono" type="tel" required autocomplete="tel" v-model="form.telefono" placeholder="7123-4567" />
                    <InputError :message="form.errors.telefono" />
                </div>

                <div class="grid gap-2">
                    <Label for="id_departamento">Departamento</Label>
                    <select id="id_departamento" v-model="form.id_departamento" required :class="claseSelect">
                        <option value="" disabled>Selecciona un departamento</option>
                        <option v-for="departamento in departamentos" :key="departamento.id_departamento" :value="departamento.id_departamento">
                            {{ departamento.nombre }}
                        </option>
                    </select>
                    <InputError :message="form.errors.id_departamento" />
                </div>

                <div class="grid gap-2">
                    <Label for="id_municipio">Municipio</Label>
                    <select
                        id="id_municipio"
                        v-model="form.id_municipio"
                        required
                        :disabled="!form.id_departamento || cargandoMunicipios"
                        :class="claseSelect"
                    >
                        <option value="" disabled>
                            {{ cargandoMunicipios ? 'Cargando…' : 'Selecciona un municipio' }}
                        </option>
                        <option v-for="municipio in municipios" :key="municipio.id_municipio" :value="municipio.id_municipio">
                            {{ municipio.nombre }}
                        </option>
                    </select>
                    <InputError :message="form.errors.id_municipio" />
                </div>

                <div class="grid gap-2">
                    <Label for="id_distrito">Distrito</Label>
                    <select
                        id="id_distrito"
                        v-model="form.id_distrito"
                        required
                        :disabled="!form.id_municipio || cargandoDistritos"
                        :class="claseSelect"
                    >
                        <option value="" disabled>
                            {{ cargandoDistritos ? 'Cargando…' : 'Selecciona un distrito' }}
                        </option>
                        <option v-for="distrito in distritos" :key="distrito.id_distrito" :value="distrito.id_distrito">
                            {{ distrito.nombre }}
                        </option>
                    </select>
                    <InputError :message="form.errors.id_distrito" />
                </div>

                <div class="grid gap-2">
                    <Label for="direccion">Dirección</Label>
                    <Input
                        id="direccion"
                        type="text"
                        required
                        autocomplete="street-address"
                        v-model="form.direccion"
                        placeholder="Colonia, calle, número de casa"
                    />
                    <InputError :message="form.errors.direccion" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <p class="text-xs text-muted-foreground">Usa mayúsculas, minúsculas y al menos un número.</p>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Repite la contraseña"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes una cuenta?
                <TextLink :href="route('login')" class="underline underline-offset-4">Inicia sesión</TextLink>
            </div>
        </form>
    </AuthBase>
</template>