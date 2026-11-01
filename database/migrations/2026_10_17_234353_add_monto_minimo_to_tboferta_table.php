<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monto mínimo de compra para las ofertas globales
     * (por ejemplo: "$5 de descuento en compras mayores a $60").
     */
    public function up(): void
    {
        Schema::table('tboferta', function (Blueprint $table): void {
            $table->decimal('monto_minimo', 10, 2)->nullable()->after('valor_descuento');
        });
    }

    public function down(): void
    {
        Schema::table('tboferta', function (Blueprint $table): void {
            $table->dropColumn('monto_minimo');
        });
    }
};