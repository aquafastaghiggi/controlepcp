<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeriadoSopro extends Model
{
    protected $table = 'feriados_sopro';

    protected $fillable = ['calendario_sopro_id', 'data', 'descricao'];

    protected $casts = ['data' => 'date'];

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioSopro::class, 'calendario_sopro_id');
    }
}
