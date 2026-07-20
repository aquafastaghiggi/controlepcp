<?php

declare(strict_types=1);

namespace App\Models\Codi;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapeamento entre SKU do CODI e SKU do ControlePCP.
 * Necessário quando os códigos diferem entre sistemas.
 * fator_conversao: multiplicar quantidade CODI para obter quantidade PCP.
 */
class CodiSkuMapping extends Model
{
    protected $table = 'codi_sku_mapping';

    protected $fillable = [
        'sku_codi',
        'sku_pcp',
        'fator_conversao',
        'ativo',
    ];

    protected $casts = [
        'fator_conversao' => 'decimal:4',
        'ativo'           => 'boolean',
    ];
}
