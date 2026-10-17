<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Municipio;
use Illuminate\Http\JsonResponse;

class UbicacionController extends Controller
{
    /**
     * Municipios de un departamento, para el select dependiente del registro.
     */
    public function municipios(Departamento $departamento): JsonResponse
    {
        return response()->json(
            $departamento->municipios()->orderBy('nombre')->get(['id_municipio', 'nombre'])
        );
    }

    /**
     * Distritos de un municipio, para el select dependiente del registro.
     */
    public function distritos(Municipio $municipio): JsonResponse
    {
        return response()->json(
            $municipio->distritos()->orderBy('nombre')->get(['id_distrito', 'nombre'])
        );
    }
}
