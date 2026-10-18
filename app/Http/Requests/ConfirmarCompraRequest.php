<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esCliente() ?? false;
    }

    /**
     * Si es envío a domicilio se piden tipo, ubicación, dirección y receptor.
     */
    public function rules(): array
    {
        return [
            'entrega' => ['required', Rule::in(['tienda', 'domicilio'])],
            'id_tipo_envio' => [
                'nullable', 'required_if:entrega,domicilio', 'integer',
                Rule::exists('tbtipos_envios', 'id_tipo_envio')->where('estado', true),
            ],
            'id_departamento' => [
                'nullable', 'required_if:entrega,domicilio', 'integer',
                Rule::exists('tbdepartamentos', 'id_departamento'),
            ],
            'id_municipio' => [
                'nullable', 'required_if:entrega,domicilio', 'integer',
                Rule::exists('tbmunicipios', 'id_municipio')->where('id_departamento', $this->input('id_departamento')),
            ],
            'id_distrito' => [
                'nullable', 'required_if:entrega,domicilio', 'integer',
                Rule::exists('tbdistritos', 'id_distrito')->where('id_municipio', $this->input('id_municipio')),
            ],
            'direccion_entrega' => ['nullable', 'required_if:entrega,domicilio', 'string', 'max:255'],
            'nombre_receptor' => ['nullable', 'required_if:entrega,domicilio', 'string', 'max:120'],
            'telefono_receptor' => ['nullable', 'required_if:entrega,domicilio', 'regex:/^[267]\d{3}-?\d{4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'required_if' => 'El campo :attribute es obligatorio para el envío a domicilio.',
            'telefono_receptor.regex' => 'El teléfono debe tener 8 dígitos y empezar con 2, 6 o 7 (ej. 7123-4567).',
            'id_municipio.exists' => 'El municipio no pertenece al departamento elegido.',
            'id_distrito.exists' => 'El distrito no pertenece al municipio elegido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'entrega' => 'forma de entrega',
            'id_tipo_envio' => 'tipo de envío',
            'id_departamento' => 'departamento',
            'id_municipio' => 'municipio',
            'id_distrito' => 'distrito',
            'direccion_entrega' => 'dirección',
            'nombre_receptor' => 'nombre de quien recibe',
            'telefono_receptor' => 'teléfono de quien recibe',
        ];
    }
}