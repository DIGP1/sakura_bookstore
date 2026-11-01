<?php

namespace App\Http\Requests\Admin;

use App\Models\TipoDescuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OfertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre_oferta' => ['required', 'string', 'max:100'],
            'id_tipo_descuento' => ['required', 'integer', Rule::exists('tbtipo_descuento', 'id_tipo_descuento')],
            'valor_descuento' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['boolean'],
            'alcance' => ['required', 'in:global,libros,categorias'],
            'monto_minimo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'productos' => ['required_if:alcance,libros', 'array'],
            'productos.*' => ['integer', Rule::exists('tbproductos', 'id_producto')],
            'categorias' => ['required_if:alcance,categorias', 'array'],
            'categorias.*' => ['integer', Rule::exists('tbcategoria', 'id_categoria')],
        ];
    }

    /**
     * Un porcentaje no puede pasar de 100.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tipo = TipoDescuento::query()->whereKey($this->input('id_tipo_descuento'))->value('nombre');

                if ($tipo === 'PORCENTAJE' && (float) $this->input('valor_descuento') > 100) {
                    $validator->errors()->add('valor_descuento', 'Un descuento en porcentaje no puede pasar de 100%.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser antes que la de inicio.',
            'productos.required_if' => 'Elegí al menos un libro.',
            'categorias.required_if' => 'Elegí al menos una categoría.',
            'valor_descuento.gt' => 'El descuento debe ser mayor que cero.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_oferta' => 'nombre',
            'id_tipo_descuento' => 'tipo de descuento',
            'valor_descuento' => 'descuento',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'monto_minimo' => 'monto mínimo',
        ];
    }
}