<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TiendaLayout from '@/layouts/TiendaLayout.vue';
import { claseSelect, dinero } from '@/lib/tienda';
import type { ResumenCarrito } from '@/types/tienda';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Store, Truck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface TipoEnvio {
    id_tipo_envio: number;
    nombre: string;
    descripcion: string | null;
    dias_estimados: number;
    costo: number;
}

interface Lugar {
    id_departamento?: number;
    id_municipio?: number;
    id_distrito?: number;
    nombre: string;
}

const props = defineProps<{
    resumen: ResumenCarrito;
    cliente: {
        nombre: string;
        telefono: string | null;
        direccion: string | null;
        id_departamento: number | null;
        id_municipio: number | null;
        id_distrito: number | null;
    };
    departamentos: Lugar[];
    tiposEnvio: TipoEnvio[];
}>();

const form = useForm({
    entrega: 'tienda' as 'tienda' | 'domicilio',
    id_tipo_envio: (props.tiposEnvio[0]?.id_tipo_envio ?? '') as number | '',
    id_departamento: (props.cliente.id_departamento ?? '') as number | '',
    id_municipio: (props.cliente.id_municipio ?? '') as number | '',
    id_distrito: (props.cliente.id_distrito ?? '') as number | '',
    direccion_entrega: props.cliente.direccion ?? '',
    nombre_receptor: props.cliente.nombre ?? '',
    telefono_receptor: props.cliente.telefono ?? '',
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

// Se cargan los selects con la ubicación guardada del cliente.
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

const tipoElegido = computed(() => props.tiposEnvio.find((tipo) => tipo.id_tipo_envio === form.id_tipo_envio));
const costoEnvio = computed(() => (form.entrega === 'domicilio' ? (tipoElegido.value?.costo ?? 0) : 0));
const totalAPagar = computed(() => Math.round((props.resumen.total + costoEnvio.value) * 100) / 100);

watch(
    () => form.entrega,
    () => form.clearErrors(),
);

function confirmar() {
    form.post(route('checkout.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Finalizar compra" />

    <TiendaLayout>
        <Link :href="route('carrito.index')" class="text-sm text-muted-foreground hover:underline">← Volver al carrito</Link>
        <h1 class="mb-6 mt-2 text-2xl font-semibold">Finalizar compra</h1>

        <form class="grid gap-8 lg:grid-cols-[1fr_340px]" @submit.prevent="confirmar">
            <div class="flex flex-col gap-6">
                <section class="rounded-lg border p-4">
                    <h2 class="mb-4 font-semibold">1. ¿Cómo querés recibir tu pedido?</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label :class="['flex cursor-pointer gap-3 rounded-md border p-4', form.entrega === 'tienda' ? 'border-primary ring-1 ring-primary' : '']">
                            <input v-model="form.entrega" type="radio" value="tienda" class="sr-only" />
                            <Store class="h-5 w-5 shrink-0" />
                            <span>
                                <span class="block font-medium">Retiro en tienda</span>
                                <span class="text-sm text-muted-foreground">Sin costo de envío</span>
                            </span>
                        </label>
                        <label :class="['flex cursor-pointer gap-3 rounded-md border p-4', form.entrega === 'domicilio' ? 'border-primary ring-1 ring-primary' : '']">
                            <input v-model="form.entrega" type="radio" value="domicilio" class="sr-only" />
                            <Truck class="h-5 w-5 shrink-0" />
                            <span>
                                <span class="block font-medium">Envío a domicilio</span>
                                <span class="text-sm text-muted-foreground">Desde {{ dinero(tiposEnvio[0]?.costo ?? 0) }}</span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.entrega" class="mt-2" />
                </section>

                <section v-if="form.entrega === 'domicilio'" class="flex flex-col gap-4 rounded-lg border p-4">
                    <h2 class="font-semibold">2. Datos del envío</h2>

                    <div class="grid gap-2">
                        <Label for="id_tipo_envio">Tipo de envío</Label>
                        <select id="id_tipo_envio" v-model="form.id_tipo_envio" :class="claseSelect">
                            <option v-for="tipo in tiposEnvio" :key="tipo.id_tipo_envio" :value="tipo.id_tipo_envio">
                                {{ tipo.nombre }} · {{ tipo.dias_estimados }} {{ tipo.dias_estimados === 1 ? 'día' : 'días' }} · {{ dinero(tipo.costo) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.id_tipo_envio" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="id_departamento">Departamento</Label>
                            <select id="id_departamento" v-model="form.id_departamento" :class="claseSelect" @change="cambiarDepartamento">
                                <option value="" disabled>Elegí uno</option>
                                <option v-for="d in departamentos" :key="d.id_departamento" :value="d.id_departamento">{{ d.nombre }}</option>
                            </select>
                            <InputError :message="form.errors.id_departamento" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="id_municipio">Municipio</Label>
                            <select id="id_municipio" v-model="form.id_municipio" :class="claseSelect" :disabled="!municipios.length" @change="cambiarMunicipio">
                                <option value="" disabled>Elegí uno</option>
                                <option v-for="m in municipios" :key="m.id_municipio" :value="m.id_municipio">{{ m.nombre }}</option>
                            </select>
                            <InputError :message="form.errors.id_municipio" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="id_distrito">Distrito</Label>
                            <select id="id_distrito" v-model="form.id_distrito" :class="claseSelect" :disabled="!distritos.length">
                                <option value="" disabled>Elegí uno</option>
                                <option v-for="d in distritos" :key="d.id_distrito" :value="d.id_distrito">{{ d.nombre }}</option>
                            </select>
                            <InputError :message="form.errors.id_distrito" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="direccion_entrega">Dirección</Label>
                        <Input id="direccion_entrega" v-model="form.direccion_entrega" maxlength="255" placeholder="Colonia, calle, número de casa, referencia" />
                        <InputError :message="form.errors.direccion_entrega" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="nombre_receptor">¿Quién recibe?</Label>
                            <Input id="nombre_receptor" v-model="form.nombre_receptor" maxlength="120" />
                            <InputError :message="form.errors.nombre_receptor" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="telefono_receptor">Teléfono de quien recibe</Label>
                            <Input id="telefono_receptor" v-model="form.telefono_receptor" maxlength="9" placeholder="7123-4567" />
                            <InputError :message="form.errors.telefono_receptor" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-4">
                    <h2 class="mb-2 font-semibold">{{ form.entrega === 'domicilio' ? '3' : '2' }}. Pago</h2>
                    <p class="text-sm text-muted-foreground">
                        Pago simulado: al confirmar, la compra se registra como pagada. No se pide ninguna tarjeta.
                    </p>
                </section>
            </div>

            <aside class="h-fit rounded-lg border p-4">
                <h2 class="mb-4 font-semibold">Resumen del pedido</h2>
                <ul class="mb-4 space-y-2 text-sm">
                    <li v-for="linea in resumen.lineas" :key="linea.id_producto" class="flex justify-between gap-3">
                        <span class="line-clamp-2">{{ linea.cantidad }} × {{ linea.nombre }}</span>
                        <span class="shrink-0">{{ dinero(linea.subtotal) }}</span>
                    </li>
                </ul>
                <dl class="space-y-2 border-t pt-3 text-sm">
                    <div v-if="resumen.ahorro_ofertas > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>Ahorro por ofertas</dt><dd>-{{ dinero(resumen.ahorro_ofertas) }}</dd>
                    </div>
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ dinero(resumen.subtotal) }}</dd></div>
                    <div v-if="resumen.descuento_global > 0" class="flex justify-between text-pink-700 dark:text-pink-400">
                        <dt>{{ resumen.oferta_global }}</dt><dd>-{{ dinero(resumen.descuento_global) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Envío</dt>
                        <dd>{{ form.entrega === 'domicilio' ? dinero(costoEnvio) : 'Retiro en tienda' }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-semibold"><dt>Total a pagar</dt><dd>{{ dinero(totalAPagar) }}</dd></div>
                </dl>

                <Button type="submit" class="mt-4 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Confirmar y pagar
                </Button>
            </aside>
        </form>
    </TiendaLayout>
</template>