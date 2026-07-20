<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tempo de setup (troca) entre dois produtos na linha.
 * Define quantos minutos são necessários para preparar a linha para produzir
 * sku_destino imediatamente após ter produzido sku_origem.
 *
 * A direção importa: setup(A→B) pode ser diferente de setup(B→A).
 * Par não cadastrado = 0 minutos (sem troca necessária).
 */
class MatrizSetup extends Model
{
    protected $table = 'matriz_setup';

    protected $fillable = [
        'linha_id',
        'sku_origem',
        'sku_destino',
        'duracao_minutos',
    ];

    protected $casts = [
        'duracao_minutos' => 'integer',
    ];

    /** Produto de onde a linha vem (antes da troca) */
    public function produtoOrigem(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'sku_origem', 'sku');
    }

    /** Produto para onde a linha vai (após a troca) */
    public function produtoDestino(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'sku_destino', 'sku');
    }

    /**
     * Busca o tempo de setup entre dois SKUs.
     * Retorna 0 se o par não estiver cadastrado (sem troca necessária).
     */
    public static function buscarDuracao(string $skuOrigem, string $skuDestino): int
    {
        $entrada = static::where('sku_origem', $skuOrigem)
            ->where('sku_destino', $skuDestino)
            ->value('duracao_minutos');

        return $entrada ?? 0;
    }
}
