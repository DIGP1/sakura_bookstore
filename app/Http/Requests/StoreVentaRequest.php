<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_cliente' => ['required', 'integer', Rule::exists('tbclientes', 'id_cliente')],
            'id_oferta' => ['nullable', 'integer', Rule::exists('tboferta', 'id_oferta')],
            'id_estado_ventas' => ['required', 'integer', Rule::exists('tbestado_ventas', 'id_estado_ventas')],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', Rule::exists('tbproductos', 'id_producto'), 'distinct'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_unitario' => ['nullable', 'numeric', 'gt:0'],
            'detalles.*.descuento_aplicado' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
