<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produto (SKU) cadastrado no sistema.
 *
 * taxa_por_hora é o campo mais crítico: define diretamente quanto tempo
 * cada ordem vai consumir. Uma taxa errada distorce toda a programação.
 *
 * referencia_setup agrupa produtos em famílias para facilitar o preenchimento
 * da matriz de setup — produtos da mesma família tendem a ter tempos de troca similares.
 */
class Produto extends Model
{
    protected $table = 'produtos';

    // O SKU é a primary key de negócio; usamos o id padrão como PK técnica
    protected $fillable = [
        'sku',
        'descricao',
        'taxa_por_hora',
        'referencia_setup',
        'linha_id',
        'ativo',
    ];

    protected $casts = [
        'taxa_por_hora' => 'decimal:2',
        'ativo'         => 'boolean',
    ];

    /** Apenas produtos ativos aparecem para seleção em novas programações */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function linha(): BelongsTo
    {
        return $this->belongsTo(Linha::class);
    }

    /** Configurações de setup onde este produto é a origem (saindo dele) */
    public function setupsOrigem(): HasMany
    {
        return $this->hasMany(MatrizSetup::class, 'sku_origem', 'sku');
    }

    /** Configurações de setup onde este produto é o destino (chegando nele) */
    public function setupsDestino(): HasMany
    {
        return $this->hasMany(MatrizSetup::class, 'sku_destino', 'sku');
    }

    /**
     * Tempo de produção estimado em minutos para uma dada quantidade e eficiência.
     * Centraliza o cálculo para evitar duplicação entre Services e testes.
     */
    public function calcularTempoProducaoMinutos(float $quantidade, float $eficienciaPercentual = 100.0): int
    {
        $taxaEfetiva = $this->taxa_por_hora * ($eficienciaPercentual / 100.0);

        if ($taxaEfetiva <= 0) {
            return 0;
        }

        return (int) round(($quantidade / $taxaEfetiva) * 60);
    }
}
