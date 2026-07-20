<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associação entre um turno e um dia da semana.
 * Permite turnos que só existem em determinados dias —
 * ex: turno noturno ativo apenas de segunda a quinta.
 *
 * Convenção de dia_semana: 1=Segunda … 7=Domingo (ISO 8601).
 */
class DiaUtil extends Model
{
    protected $table = 'dias_uteis';

    protected $fillable = [
        'intervalo_id',
        'dia_semana',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
    ];

    /** Nomes dos dias para exibição */
    public const NOMES_DIAS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** Turno ao qual esta configuração de dia pertence */
    public function intervalo(): BelongsTo
    {
        return $this->belongsTo(Intervalo::class);
    }

    /** Retorna o nome do dia em português */
    public function getNomeDiaAttribute(): string
    {
        return self::NOMES_DIAS[$this->dia_semana] ?? "Dia {$this->dia_semana}";
    }
}
