<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarDevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esCliente() ?? false;
    }

    public function rules(): array
    {
        return [
            'id_motivo_devolucion' => ['required', 'integer', Rule::exists('tbmotivos_devolucion', 'id_motivo_devolucion')],
            'id_tipo_reembolso' => ['required', 'integer', Rule::exists('tbtipo_reembolso', 'id_tipo_reembolso')],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_detalle_venta' => ['required', 'integer'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_motivo_devolucion' => 'motivo',
            'id_tipo_reembolso' => 'tipo de reembolso',
            'lineas' => 'libros a devolver',
            'lineas.*.cantidad' => 'cantidad',
        ];
    }
}