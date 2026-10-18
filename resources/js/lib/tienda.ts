const formatoDinero = new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' });

/** Formatea un monto en dólares: 12.5 → $12.50 */
export function dinero(monto: number | string | null | undefined): string {
    return formatoDinero.format(Number(monto ?? 0));
}

/** Mismas clases del Input de shadcn, para los <select> nativos. */
export const claseSelect =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';