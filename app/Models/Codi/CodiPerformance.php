<?php

declare(strict_types=1);

namespace App\Models\Codi;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabela de cadência de produção do CODI (taxa padrão por item/linha).
 * NÃO é OEE em tempo real — é a referência de performance esperada.
 * Sincronizado via codi:sincronizar --tipo=performance.
 */
class CodiPerformance extends Model
{
    protected $table = 'codi_performance';

    protected $fillable = [
        'codigo_performance',
        'codigo_recurso',
        'nome_recurso',
        'codigo_item',
        'ordem_producao',
        'disponibilidade',
        'performance',
        'oee',
        'estado_atual',
        'quantidade_produzida',
        'dados_raw',
        'sincronizado_em',
    ];

    protected $casts = [
        'codigo_performance' => 'integer',
        'disponibilidade'    => 'decimal:2',
        'performance'        => 'decimal:2',
        'oee'                => 'decimal:2',
        'quantidade_produzida' => 'decimal:2',
        'dados_raw'          => 'array',
        'sincronizado_em'    => 'datetime',
    ];
}
