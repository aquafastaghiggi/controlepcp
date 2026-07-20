<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoSequenciaSopro extends Model
{
    protected $table = 'resultado_sequencia_sopro';

    protected $fillable = [
        'programacao_sopro_id',
        'item_id',
        'tipo',
        'sku',
        'inicio',
        'fim',
        'duracao_minutos',
        'quantidade_estimada',
        'memoria_calculo',
    ];

    protected $casts = [
        'inicio'              => 'datetime',
        'fim'                 => 'datetime',
        'duracao_minutos'     => 'integer',
        'quantidade_estimada' => 'decimal:2',
    ];

    public function programacao(): BelongsTo
    {
        return $this->belongsTo(ProgramacaoSopro::class, 'programacao_sopro_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProgramacaoSopro::class, 'item_id');
    }

    public function ehSetup(): bool
    {
        return $this->tipo === 'setup';
    }

    public function ehProducao(): bool
    {
        return $this->tipo === 'producao';
    }
}
