<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloco de tempo alocado no resultado do sequenciamento.
 *
 * Cada item pode gerar múltiplos registros quando a produção cruza turnos ou dias.
 * Tipos:
 *   setup    → troca de produto entre dois SKUs consecutivos
 *   producao → bloco de fabricação real
 *
 * memoria_calculo armazena o detalhamento legível para auditoria:
 * "12/06 turno 07:10-11:28 | usado 08:00-11:28 = 3h28m | total = 5h00m"
 */
class ResultadoSequencia extends Model
{
    protected $table = 'resultado_sequencia';

    protected $fillable = [
        'programacao_id',
        'item_id',
        'tipo',
        'sku',
        'inicio',
        'fim',
        'duracao_minutos',
        'quantidade_estimada',
        'memoria_calculo',
    ];

    protected $casts = [
        'inicio'               => 'datetime',
        'fim'                  => 'datetime',
        'duracao_minutos'      => 'integer',
        'quantidade_estimada'  => 'decimal:2',
    ];

    /** Programação à qual este resultado pertence */
    public function programacao(): BelongsTo
    {
        return $this->belongsTo(Programacao::class);
    }

    /** Item de origem deste bloco (null para blocos de setup) */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProgramacao::class, 'item_id');
    }

    public function ehSetup(): bool
    {
        return $this->tipo === 'setup';
    }

    public function ehProducao(): bool
    {
        return $this->tipo === 'producao';
    }
}
