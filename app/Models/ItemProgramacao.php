<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item (ordem individual) de uma programação de produção.
 *
 * SKU e descricao_produto são desnormalizados intencionalmente:
 * isso preserva o histórico mesmo se o cadastro do produto for alterado depois.
 * A sequencia define a ordem de produção e, consequentemente, o tempo total de setup.
 */
class ItemProgramacao extends Model
{
    protected $table = 'itens_programacao';

    protected $fillable = [
        'programacao_id',
        'sequencia',
        'numero_op',
        'sku',
        'descricao_produto',
        'quantidade',
    ];

    protected $casts = [
        'sequencia'  => 'integer',
        'quantidade' => 'decimal:2',
    ];

    /** Programação à qual este item pertence */
    public function programacao(): BelongsTo
    {
        return $this->belongsTo(Programacao::class);
    }

    /** Cadastro atual do produto (pode ser null se produto foi excluído) */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'sku', 'sku');
    }

    /** Blocos de resultado calculados para este item específico */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoSequencia::class, 'item_id');
    }
}
