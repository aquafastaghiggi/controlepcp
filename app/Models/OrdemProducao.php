<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordem de Producao — unidade atomica de trabalho no modulo de PCP.
 *
 * Ciclo de vida valido (ver TRANSICOES_POSSIVEIS):
 *   pendente -> programada -> em_producao -> concluida
 *                  |               |
 *              (volta) pendente  cancelada
 *
 * O numero_op e gerado automaticamente pelo evento created do model ou pelo
 * servico de importacao quando nao vem preenchido da origem.
 *
 * sku e descricao_produto sao denormalizados intencionalmente: garantem que
 * alteracoes futuras no cadastro de produtos nao corrompam o historico das OPs.
 */
class OrdemProducao extends Model
{
    protected $table = 'ordens_producao';

    // -------------------------------------------------------------------------
    // Status constants
    // -------------------------------------------------------------------------

    public const STATUS_PENDENTE    = 'pendente';
    public const STATUS_PROGRAMADA  = 'programada';
    public const STATUS_EM_PRODUCAO = 'em_producao';
    public const STATUS_CONCLUIDA   = 'concluida';
    public const STATUS_CANCELADA   = 'cancelada';

    /**
     * Mapa de transicoes validas por status atual.
     * Utilizado pelo OrdemProducaoService para validar mudancas de estado.
     */
    public const TRANSICOES_POSSIVEIS = [
        self::STATUS_PENDENTE    => [self::STATUS_PROGRAMADA, self::STATUS_CANCELADA],
        self::STATUS_PROGRAMADA  => [self::STATUS_EM_PRODUCAO, self::STATUS_PENDENTE, self::STATUS_CANCELADA],
        self::STATUS_EM_PRODUCAO => [self::STATUS_CONCLUIDA, self::STATUS_CANCELADA],
        self::STATUS_CONCLUIDA   => [],
        self::STATUS_CANCELADA   => [],
    ];

    // -------------------------------------------------------------------------
    // Eloquent config
    // -------------------------------------------------------------------------

    use HasFactory;

    protected $fillable = [
        'numero_op',
        'linha_id',
        'sku',
        'descricao_produto',
        'quantidade',
        'data_entrega',
        'prioridade',
        'status',
        'origem',
        'observacoes',
        'programacao_id',
    ];

    protected $casts = [
        'data_entrega' => 'date',
        'quantidade'   => 'decimal:3',
        'prioridade'   => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /** Linha de producao onde esta OP sera (ou foi) executada */
    public function linha(): BelongsTo
    {
        return $this->belongsTo(Linha::class);
    }

    /** Programacao de sequenciamento a qual esta OP esta vinculada */
    public function programacao(): BelongsTo
    {
        return $this->belongsTo(Programacao::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Apenas ordens aguardando programacao */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }

    /** Ordens em andamento: pendente, programada ou em producao */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDENTE,
            self::STATUS_PROGRAMADA,
            self::STATUS_EM_PRODUCAO,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retorna todos os status que possuem pelo menos uma transicao valida de saida,
     * i.e., os status a partir dos quais a OP ainda pode mudar de estado.
     *
     * @return list<string>
     */
    public static function statusPermitidos(): array
    {
        return array_keys(self::TRANSICOES_POSSIVEIS);
    }

    /**
     * Verifica se a transicao do status atual para $novoStatus e permitida.
     */
    public function podeTransicionarPara(string $novoStatus): bool
    {
        return in_array($novoStatus, self::TRANSICOES_POSSIVEIS[$this->status] ?? [], true);
    }
}