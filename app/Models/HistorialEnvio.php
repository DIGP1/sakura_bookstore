<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEnvio extends LibreriaModel
{
    protected $table = 'tbhistorial_envio';

    protected $primaryKey = 'id_historial';

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }
    
      public function envio(): BelongsTo
      {
          return $this->belongsTo(Envio::class, 'id_envio', 'id_envio');
      }

      public function estado(): BelongsTo
      {
          return $this->belongsTo(EstadoEnvio::class, 'id_estado_envio', 'id_estado_envio');
      }
}
