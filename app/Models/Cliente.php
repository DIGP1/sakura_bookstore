<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends LibreriaModel
{
    protected $table = 'tbclientes';

    protected $primaryKey = 'id_cliente';

    protected function casts(): array
    {
        return ['fecha_registro' => 'date'];
    }

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'id_distrito', 'id_distrito');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'id_cliente', 'id_cliente');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
