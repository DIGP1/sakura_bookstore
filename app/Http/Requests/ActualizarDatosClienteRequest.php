<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarDatosClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esCliente() ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'regex:/^[267]\d{3}-?\d{4}$/'],
            'id_departamento' => ['required', 'integer', Rule::exists('tbdepartamentos', 'id_departamento')],
            'id_municipio' => [
                'required', 'integer',
                Rule::exists('tbmunicipios', 'id_municipio')->where('id_departamento', $this->input('id_departamento')),
            ],
            'id_distrito' => [
                'required', 'integer',
                Rule::exists('tbdistritos', 'id_distrito')->where('id_municipio', $this->input('id_municipio')),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos y empezar con 2, 6 o 7 (ej. 7123-4567).',
            'id_municipio.exists' => 'El municipio no pertenece al departamento elegido.',
            'id_distrito.exists' => 'El distrito no pertenece al municipio elegido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'telefono' => 'teléfono',
            'id_departamento' => 'departamento',
            'id_municipio' => 'municipio',
            'id_distrito' => 'distrito',
            'direccion' => 'dirección',
        ];
    }
}