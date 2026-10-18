<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use RuntimeException;
use Throwable;

class RegistrarClienteService
{
    private ?int $clienteNeo4jId = null;

    private bool $neo4jCommitCompleted = false;

    public function __construct(private readonly ClientInterface $neo4j) {}

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
                $this->guardarEnNeo4j($cliente);
                $this->neo4jCommitCompleted = true;

                return $user;
            });
        } catch (Throwable $exception) {
            if ($this->neo4jCommitCompleted && $this->clienteNeo4jId !== null) {
                try {
                    $this->eliminarDeNeo4j($this->clienteNeo4jId);
                } catch (Throwable $compensationException) {
                    report($compensationException);
                }
            }

            throw $exception;
        }
    }

    private function guardarEnNeo4j(Cliente $cliente): void
    {
        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($cliente): void {
            $distrito = $transaction->run(
                'MATCH (d:Distrito {id_distrito: $id_distrito})
                 RETURN d.id_distrito AS id_distrito',
                ['id_distrito' => (int) $cliente->id_distrito],
            );

            if ($distrito->count() === 0) {
                throw new RuntimeException('El distrito no existe en Neo4j.');
            }

            $transaction->run(
                'MERGE (c:Cliente {id_cliente: $id_cliente})
                 SET c.nombre = $nombre,
                     c.email = $email,
                     c.fecha_registro = date($fecha_registro)
                 WITH c
                 MATCH (d:Distrito {id_distrito: $id_distrito})
                 MERGE (c)-[:RESIDE_EN]->(d)',
                [
                    'id_cliente' => (int) $cliente->id_cliente,
                    'nombre' => $cliente->nombre,
                    'email' => $cliente->email,
                    'fecha_registro' => $cliente->fecha_registro->toDateString(),
                    'id_distrito' => (int) $cliente->id_distrito,
                ],
            );
        });
    }

    private function eliminarDeNeo4j(int $clienteId): void
    {
        $this->neo4j->writeTransaction(function (TransactionInterface $transaction) use ($clienteId): void {
            $transaction->run(
                'MATCH (c:Cliente {id_cliente: $id_cliente})
                 DETACH DELETE c',
                ['id_cliente' => $clienteId],
            );
        });
    }
}
