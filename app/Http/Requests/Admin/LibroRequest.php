<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\LibroController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdmin() ?? false;
    }

    /**
     * El ISBN se guarda solo con números, aunque lo escriban con guiones.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'isbn' => preg_replace('/\D/', '', (string) $this->input('isbn')),
        ]);
    }

    public function rules(): array
    {
        $idActual = $this->route('producto')?->id_producto;

        return [
            'isbn' => [
                'required', 'regex:/^(\d{10}|\d{13})$/',
                Rule::unique('tbproductos', 'isbn')->ignore($idActual, 'id_producto'),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'id_editorial' => ['required', 'integer', Rule::exists('tbeditoriales', 'id_editorial')],
            'anio_publicacion' => ['nullable', 'integer', 'between:1450,'.now()->year],
            'idioma' => ['required', 'string', 'max:30'],
            'formato' => ['required', Rule::in(array_keys(LibroController::FORMATOS))],
            'num_paginas' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'precio_unitario' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
            'autores' => ['required', 'array', 'min:1'],
            'autores.*' => ['integer', Rule::exists('tbautores', 'id_autor')],
            'categorias' => ['required', 'array', 'min:1'],
            'categorias.*' => ['integer', Rule::exists('tbcategoria', 'id_categoria')],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.regex' => 'El ISBN debe tener 10 o 13 dígitos.',
            'isbn.unique' => 'Ya existe un libro con ese ISBN.',
            'precio_unitario.gt' => 'El precio debe ser mayor que cero.',
            'autores.required' => 'Elegí al menos un autor.',
            'categorias.required' => 'Elegí al menos una categoría.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'título',
            'id_editorial' => 'editorial',
            'anio_publicacion' => 'año de publicación',
            'num_paginas' => 'número de páginas',
            'precio_unitario' => 'precio',
            'stock_minimo' => 'stock mínimo',
        ];
    }
}