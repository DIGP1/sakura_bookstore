<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class RegistrarClienteService
{
    private ?int $clienteNeo4jId = null;

    private bool $neo4jCommitCompleted = false;

    public function __construct(private readonly SincronizarNeo4jService $neo4j) {}

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     telefono: string,
     *     id_distrito: int,
     *     direccion: string
     * } $datos
     */
    public function handle(array $datos): User
    {
        $this->clienteNeo4jId = null;
        $this->neo4jCommitCompleted = false;

        try {
            return DB::transaction(function () use ($datos): User {
                $user = User::create([
                    'name' => $datos['name'],
                    'email' => $datos['email'],
                    'password' => Hash::make($datos['password']),
                ]);
                $user->rol = User::ROL_CLIENTE;
                $user->save();

                $cliente = Cliente::create([
                    'id_user' => $user->id,
                    'nombre' => $datos['name'],
                    'email' => $datos['email'],
                    'telefono' => substr($datos['telefono'], 0, 4).'-'.substr($datos['telefono'], 4),
                    'id_distrito' => $datos['id_distrito'],
                    'direccion' => $datos['direccion'],
                    'fecha_registro' => now()->toDateString(),
                ]);

                $this->clienteNeo4jId = (int) $cliente->id_cliente;
                $this->neo4j->cliente($cliente);
                $this->neo4jCommitCompleted = true;

                return $user;
            });
        } catch (Throwable $exception) {
            if ($this->neo4jCommitCompleted && $this->clienteNeo4jId !== null) {
                try {
                    $this->neo4j->eliminarCliente($this->clienteNeo4jId);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }
    }
}
