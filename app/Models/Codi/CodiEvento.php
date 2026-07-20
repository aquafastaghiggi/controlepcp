<?php

declare(strict_types=1);

namespace App\Models\Codi;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento individual de produção sincronizado do CODI.
 * Tipos: PRODUCAO, SETUP, REJEITO, PARADA.
 */
class CodiEvento extends Model
{
    protected $table = 'codi_eventos';

    protected $fillable = [
        'codigo_evento',
        'codigo_recurso',
        'ordem_producao',
        'codigo_item',
        'tipo_evento',
        'quantidade',
        'inicio_evento',
        'fim_evento',
        'duracao_minutos',
        'dados_raw',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:2',
        'inicio_evento'  => 'datetime',
        'fim_evento'     => 'datetime',
        'dados_raw'      => 'array',
    ];
}
