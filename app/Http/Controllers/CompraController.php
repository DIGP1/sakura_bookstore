<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveCompraRequest;
use App\Models\Compra;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;

class CompraController extends Controller
{
    public function receive(ReceiveCompraRequest $request, Compra $compra, InventarioService $inventory): JsonResponse
    {
        return response()->json($inventory->receivePurchase($compra, $request->validated('id_estado_compra')));
    }
}
