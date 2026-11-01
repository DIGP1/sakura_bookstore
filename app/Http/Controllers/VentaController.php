<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Venta;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;

class VentaController extends Controller
{
    public function store(StoreVentaRequest $request, InventarioService $inventory): JsonResponse
    {
        $sale = $inventory->createSale(
            $request->safe()->only(['id_cliente', 'id_oferta', 'id_estado_ventas']),
            $request->validated('detalles'),
        );

        return response()->json($sale, 201);
    }

    public function cancel(Venta $venta, InventarioService $inventory): JsonResponse
    {
        return response()->json($inventory->cancelSale($venta));
    }
}
