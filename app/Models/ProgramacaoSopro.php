<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Codi\CodiEficienciaSopro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramacaoSopro extends Model
{
    protected $table = 'programacoes_sopro';

    protected $fillable = [
        'maquina_id',
        'numero_op',
        'status',
        'eficiencia',
        'dias_selecionados',
        'otimizado',
        'origem',
        'data_inicio_planejada',
        'calculado_em',
        'arquivada_em',
    ];

    protected $casts = [
        'data_inicio_planejada' => 'datetime',
        'calculado_em'          => 'datetime',
        'arquivada_em'          => 'datetime',
        'eficiencia'            => 'decimal:2',
        'otimizado'             => 'boolean',
        'dias_selecionados'     => 'array',
    ];

    public const STATUSES_EDITAVEIS = ['rascunho', 'calculada'];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemProgramacaoSopro::class, 'programacao_sopro_id')->orderBy('sequencia');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoSequenciaSopro::class, 'programacao_sopro_id');
    }

    public function eficiencias(): HasMany
    {
        return $this->hasMany(CodiEficienciaSopro::class, 'programacao_sopro_id');
    }

    public function scopeEditaveis($query)
    {
        return $query->whereIn('status', self::STATUSES_EDITAVEIS);
    }

    public function scopeConfirmadas($query)
    {
        return $query->where('status', 'confirmada');
    }

    public function scopeAtiva($query)
    {
        return $query->where('status', 'confirmada');
    }

    public function scopeHistorico($query)
    {
        return $query->where('status', 'arquivada')->orderByDesc('arquivada_em');
    }

    public function estaEditavel(): bool
    {
        return in_array($this->status, self::STATUSES_EDITAVEIS, true);
    }
}
