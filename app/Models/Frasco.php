<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Frasco extends Model
{
    protected $fillable = [
        'sku',
        'descricao',
        'material',
        'molde',
        'cor',
        'taxa_por_hora',
        'maquina_id',
        'ativo',
    ];

    protected $casts = [
        'ativo'         => 'boolean',
        'taxa_por_hora' => 'decimal:2',
    ];

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }
}
