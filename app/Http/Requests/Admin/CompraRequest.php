<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'id_proveedor' => ['required', 'integer', Rule::exists('tbproveedor', 'id_proveedor')],
            'numero_factura' => [
                'required', 'string', 'max:30',
                Rule::unique('tbcompras', 'numero_factura')->where('id_proveedor', $this->input('id_proveedor')),
            ],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_producto' => ['required', 'integer', 'distinct', Rule::exists('tbproductos', 'id_producto')],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_factura.unique' => 'Ese proveedor ya tiene una compra con ese número de factura.',
            'lineas.required' => 'Agregá al menos un libro a la compra.',
            'lineas.*.id_producto.required' => 'Elegí el libro de cada línea.',
            'lineas.*.id_producto.distinct' => 'Hay un libro repetido. Juntá las cantidades en una sola línea.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'lineas.*.costo_unitario.gt' => 'El costo debe ser mayor que cero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_proveedor' => 'proveedor',
            'numero_factura' => 'número de factura',
            'lineas.*.cantidad' => 'cantidad',
            'lineas.*.costo_unitario' => 'costo',
        ];
    }
}