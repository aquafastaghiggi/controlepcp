<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatrizSetupSopro extends Model
{
    protected $table = 'matriz_setup_sopro';

    protected $fillable = [
        'maquina_id',
        'sku_origem',
        'sku_destino',
        'duracao_minutos',
        'tipo_setup',
    ];

    protected $casts = [
        'duracao_minutos' => 'integer',
    ];

    /**
     * Busca a duração de setup entre dois SKUs.
     * Retorna 0 se o par não estiver cadastrado.
     */
    public static function buscarDuracao(string $skuOrigem, string $skuDestino, ?int $maquinaId = null): int
    {
        $query = static::where('sku_origem', $skuOrigem)
            ->where('sku_destino', $skuDestino);

        if ($maquinaId !== null) {
            $query->where('maquina_id', $maquinaId);
        }

        return $query->value('duracao_minutos') ?? 0;
    }

    /**
     * Busca o tipo de setup entre dois SKUs.
     * Retorna null se o par não estiver cadastrado.
     */
    public static function buscarTipo(string $skuOrigem, string $skuDestino, ?int $maquinaId = null): ?string
    {
        $query = static::where('sku_origem', $skuOrigem)
            ->where('sku_destino', $skuDestino);

        if ($maquinaId !== null) {
            $query->where('maquina_id', $maquinaId);
        }

        return $query->value('tipo_setup');
    }

    public function frascoOrigem()
    {
        return $this->belongsTo(Frasco::class, 'sku_origem', 'sku');
    }

    public function frascoDestino()
    {
        return $this->belongsTo(Frasco::class, 'sku_destino', 'sku');
    }
}
