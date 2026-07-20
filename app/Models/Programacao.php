<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programação de produção — sessão de sequenciamento para uma linha.
 *
 * Ciclo de vida:
 *   rascunho → (usuário monta os itens) → calculada → (revisão) → confirmada → cancelada
 *   Quando uma nova programação é confirmada para a mesma linha, a anterior vai para arquivada.
 *
 * O campo eficiencia permite simular o impacto de uma linha mais lenta antes
 * de confirmar: 85% significa que cada item vai levar ~17.6% mais tempo.
 */
class Programacao extends Model
{
    protected $table = 'programacoes';

    protected $fillable = [
        'linha_id',
        'numero_op',
        'descricao',
        'data_inicio_planejada',
        'eficiencia',
        'dias_selecionados',
        'status',
        'origem',
        'otimizado',
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

    /** Statuses que permitem edição dos itens */
    public const STATUSES_EDITAVEIS = ['rascunho', 'calculada'];

    /** Linha onde esta programação será executada */
    public function linha(): BelongsTo
    {
        return $this->belongsTo(Linha::class);
    }

    /** Itens (ordens) que compõem esta programação, na ordem de sequência */
    public function itens(): HasMany
    {
        return $this->hasMany(ItemProgramacao::class, 'programacao_id')->orderBy('sequencia');
    }

    /** Resultado calculado do sequenciamento (setup + blocos de produção) */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoSequencia::class, 'programacao_id');
    }

    /** KPIs de eficiência calculados pelo EficienciaCalculator */
    public function eficiencias(): HasMany
    {
        return $this->hasMany(\App\Models\Codi\CodiEficiencia::class, 'programacao_id');
    }

    /** Apenas programações ainda editáveis */
    public function scopeEditaveis($query)
    {
        return $query->whereIn('status', self::STATUSES_EDITAVEIS);
    }

    /** Apenas programações confirmadas — usadas em relatórios de execução */
    public function scopeConfirmadas($query)
    {
        return $query->where('status', 'confirmada');
    }

    /** Programação ativa da linha (confirmada, não arquivada) */
    public function scopeAtiva($query)
    {
        return $query->where('status', 'confirmada');
    }

    /** Programações arquivadas — histórico de versões anteriores */
    public function scopeHistorico($query)
    {
        return $query->where('status', 'arquivada')->orderByDesc('arquivada_em');
    }

    public function estaEditavel(): bool
    {
        return in_array($this->status, self::STATUSES_EDITAVEIS, true);
    }
}
