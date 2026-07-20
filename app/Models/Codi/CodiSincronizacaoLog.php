<?php

declare(strict_types=1);

namespace App\Models\Codi;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log de cada execução de sincronização com o CODI.
 */
class CodiSincronizacaoLog extends Model
{
    protected $table = 'codi_sincronizacao_log';

    protected $fillable = [
        'tipo',
        'status',
        'registros_processados',
        'registros_novos',
        'registros_atualizados',
        'erro_mensagem',
        'duracao_segundos',
    ];
}
