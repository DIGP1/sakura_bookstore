import { reactive } from 'vue';

// Opciones de la ventana de confirmación
export interface OpcionesConfirmar {
    titulo?: string;
    mensaje: string;
    boton?: string; // texto del botón de aceptar
    peligro?: boolean; // true = botón rojo (eliminar, anular, rechazar)
}

// Estado compartido: una sola ventana para todo el panel
export const estadoConfirmar = reactive({
    abierto: false,
    titulo: '',
    mensaje: '',
    boton: 'Confirmar',
    peligro: false,
    responder: (_respuesta: boolean) => {},
});

// Reemplazo de confirm() del navegador: abre la ventana y espera la respuesta (true o false)
export function confirmar(opciones: OpcionesConfirmar): Promise<boolean> {
    return new Promise((resolver) => {
        Object.assign(estadoConfirmar, {
            abierto: true,
            titulo: opciones.titulo ?? '¿Estás seguro?',
            mensaje: opciones.mensaje,
            boton: opciones.boton ?? 'Confirmar',
            peligro: opciones.peligro ?? false,
            responder: (respuesta: boolean) => {
                estadoConfirmar.abierto = false;
                resolver(respuesta);
            },
        });
    });
}