<?php

declare(strict_types=1);

namespace App\Models\Codi;

use App\Models\ProgramacaoSopro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodiEficienciaSopro extends Model
{
    protected $table = 'codi_eficiencia_sopro';

    protected $fillable = [
        'programacao_sopro_id',
        'numero_op',
        'sku',
        'quantidade_programada',
        'quantidade_realizada',
        'tempo_padrao_minutos',
        'tempo_padrao_nominal',
        'tempo_real_minutos',
        'tempo_parado_minutos',
        'inicio_previsto',
        'fim_previsto',
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
        'inicio_previsto' => 'datetime',
        'fim_previsto'    => 'datetime',
        'inicio_real'     => 'datetime',
        'fim_real'        => 'datetime',
        'calculado_em'    => 'datetime',
    ];

    public function programacao(): BelongsTo
    {
        return $this->belongsTo(ProgramacaoSopro::class, 'programacao_sopro_id');
    }
}
