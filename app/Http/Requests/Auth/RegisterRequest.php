<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Limpia los datos antes de validarlos:
     * el correo en minúsculas y el teléfono solo con números.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'telefono' => preg_replace('/\D/', '', (string) $this->input('telefono')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:120',
                Rule::unique(User::class, 'email'),
                Rule::unique('tbclientes', 'email'),
            ],
            'telefono' => ['required', 'regex:/^[267]\d{7}$/'],
            'id_departamento' => [
                'required', 'integer',
                Rule::exists('tbdepartamentos', 'id_departamento'),
            ],
            'id_municipio' => [
                'required', 'integer',
                Rule::exists('tbmunicipios', 'id_municipio')
                    ->where('id_departamento', $this->input('id_departamento')),
            ],
            'id_distrito' => [
                'required', 'integer',
                Rule::exists('tbdistritos', 'id_distrito')
                    ->where('id_municipio', $this->input('id_municipio')),
            ],
            'direccion' => ['required', 'string', 'max:255'],
            'password' => [
                'required', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe tener 8 dígitos y empezar con 2, 6 o 7.',
            'id_municipio.exists' => 'El municipio no pertenece al departamento elegido.',
            'id_distrito.exists' => 'El distrito no pertenece al municipio elegido.',
        ];
    }
}