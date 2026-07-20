<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervaloSopro extends Model
{
    protected $table = 'intervalos_sopro';

    protected $fillable = [
        'calendario_sopro_id',
        'nome',
        'hora_inicio',
        'hora_fim',
        'ordem',
        'ativo',
    ];

    protected $casts = ['ativo' => 'boolean'];

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioSopro::class, 'calendario_sopro_id');
    }
}
