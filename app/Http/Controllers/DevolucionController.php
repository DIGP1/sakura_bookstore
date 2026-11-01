<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveDevolucionRequest;
use App\Models\Devolucion;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;

class DevolucionController extends Controller
{
    public function approve(ApproveDevolucionRequest $request, Devolucion $devolucion, InventarioService $inventory): JsonResponse
    {
        return response()->json($inventory->approveReturn($devolucion, $request->validated('id_estado_devolucion')));
    }
}
