<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feriado ou parada programada dentro de um calendário.
 * Em datas cadastradas aqui, nenhum turno é disponibilizado para produção,
 * independente dos dias_uteis configurados nos intervalos.
 */
class Feriado extends Model
{
    protected $table = 'feriados';

    protected $fillable = [
        'calendario_id',
        'data',
        'descricao',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    /** Calendário ao qual este feriado pertence */
    public function calendario(): BelongsTo
    {
        return $this->belongsTo(Calendario::class);
    }
}
