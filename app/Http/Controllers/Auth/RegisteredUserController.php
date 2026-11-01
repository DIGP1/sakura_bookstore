<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Departamento;
use App\Services\RegistrarClienteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Muestra el formulario de registro con la lista de departamentos.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'departamentos' => Departamento::orderBy('nombre')->get(['id_departamento', 'nombre']),
        ]);
    }

    /**
     * Registra al usuario y su cliente en PostgreSQL y Neo4j.
     */
    public function store(RegisterRequest $request, RegistrarClienteService $registrarCliente): RedirectResponse
    {
        $datos = $request->validated();

        $user = $registrarCliente->handle($datos);

        event(new Registered($user));

        Auth::login($user);

        return to_route('home');
    }
}
