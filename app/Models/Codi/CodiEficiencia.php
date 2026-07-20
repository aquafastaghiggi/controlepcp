<?php

declare(strict_types=1);

namespace App\Models\Codi;

use App\Models\Programacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado do cruzamento previsto (PCP) × realizado (CODI) por OP.
 * Populado pelo EficienciaCalculator.
 */
class CodiEficiencia extends Model
{
    protected $table = 'codi_eficiencia';

    protected $fillable = [
        'programacao_id',
        'numero_op',
        'sku',
        'codigo_recurso',
        'quantidade_programada',
        'tempo_padrao_minutos',
        'tempo_padrao_nominal',
        'inicio_previsto',
        'fim_previsto',
        'quantidade_realizada',
        'tempo_real_minutos',
        'tempo_parado_minutos',
        'inicio_real',
        'fim_real',
        'eficiencia_quantidade',
        'performance_tempo',
        'disponibilidade',
        'oee',
        'produtividade',
        'desvio_quantidade',
        'desvio_quantidade_pct',
        'desvio_tempo_horas',
        'desvio_prazo_dias',
        'status',
        'calculado_em',
    ];

    protected $casts = [
        'quantidade_programada' => 'decimal:2',
        'quantidade_realizada'  => 'decimal:2',
        'eficiencia_quantidade' => 'decimal:2',
        'performance_tempo'     => 'decimal:2',
        'disponibilidade'       => 'decimal:2',
        'oee'                   => 'decimal:2',
        'produtividade'         => 'decimal:2',
        'desvio_quantidade'     => 'decimal:2',
        'desvio_quantidade_pct' => 'decimal:2',
        'desvio_tempo_horas'    => 'decimal:2',
        'inicio_previsto'       => 'datetime',
        'fim_previsto'          => 'datetime',
        'inicio_real'           => 'datetime',
        'fim_real'              => 'datetime',
        'calculado_em'          => 'datetime',
    ];

    public function programacao(): BelongsTo
    {
        return $this->belongsTo(Programacao::class);
    }
}
