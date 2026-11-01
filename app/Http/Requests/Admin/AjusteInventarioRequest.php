<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AjusteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:entrada,salida'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'observacion' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'observacion.required' => 'Explicá el motivo del ajuste (por ejemplo: "2 libros dañados por humedad").',
            'observacion.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['tipo' => 'tipo de ajuste', 'observacion' => 'motivo'];
    }
}