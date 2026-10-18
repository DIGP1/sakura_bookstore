export type Disponibilidad = 'disponible' | 'pocos' | 'agotado';

export interface LibroTarjeta {
    id_producto: number;
    nombre: string;
    autores: string;
    stock: number;
    disponibilidad: Disponibilidad;
    precio: number;
    descuento: number;
    precio_final: number;
    id_oferta: number | null;
    oferta: string | null;
}

export interface FichaLibro extends LibroTarjeta {
    isbn: string;
    editorial: string | null;
    anio_publicacion: number | null;
    idioma: string | null;
    formato: string | null;
    num_paginas: number | null;
    categorias: Opcion[];
}

export interface LineaCarrito extends LibroTarjeta {
    cantidad: number;
    ahorro: number;
    subtotal: number;
}

export interface ResumenCarrito {
    lineas: LineaCarrito[];
    cantidad_libros: number;
    ahorro_ofertas: number;
    subtotal: number;
    descuento_global: number;
    oferta_global: string | null;
    id_oferta_global: number | null;
    total: number;
}

export interface Opcion {
    id: number;
    nombre: string;
}

export interface EnlacePagina {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginado<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: EnlacePagina[];
}