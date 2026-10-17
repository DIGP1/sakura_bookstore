<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
     * Registra al usuario y su cliente en una sola transacción.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $user = DB::transaction(function () use ($datos): User {
            $user = new User([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
            ]);
            $user->rol = User::ROL_CLIENTE;
            $user->save();

            Cliente::create([
                'id_user' => $user->id,
                'nombre' => $datos['name'],
                'email' => $datos['email'],
                'telefono' => substr($datos['telefono'], 0, 4).'-'.substr($datos['telefono'], 4),
                'id_distrito' => $datos['id_distrito'],
                'direccion' => $datos['direccion'],
                'fecha_registro' => now()->toDateString(),
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return to_route('home');
    }
}
