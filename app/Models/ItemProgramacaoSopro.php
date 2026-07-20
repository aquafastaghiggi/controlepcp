<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemProgramacaoSopro extends Model
{
    protected $table = 'itens_programacao_sopro';

    protected $fillable = [
        'programacao_sopro_id',
        'sku',
        'descricao_produto',
        'quantidade',
        'numero_op',
        'sequencia',
        'data_programada',
    ];

    protected $casts = [
        'sequencia'       => 'integer',
        'quantidade'      => 'decimal:2',
        'data_programada' => 'date',
    ];

    public function programacao(): BelongsTo
    {
        return $this->belongsTo(ProgramacaoSopro::class, 'programacao_sopro_id');
    }

    public function frasco(): BelongsTo
    {
        return $this->belongsTo(Frasco::class, 'sku', 'sku');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoSequenciaSopro::class, 'item_id');
    }
}
