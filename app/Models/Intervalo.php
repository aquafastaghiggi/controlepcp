<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de trabalho dentro de um calendário.
 * Define o bloco de horário disponível para produção (ex: 07:10–11:28).
 * Suporta turnos overnight: hora_fim < hora_inicio significa que cruza meia-noite.
 */
class Intervalo extends Model
{
    protected $table = 'intervalos';

    protected $fillable = [
        'calendario_id',
        'nome',
        'ordem',
        'hora_inicio',
        'hora_fim',
        'ativo',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'ativo' => 'boolean',
    ];

    /** Calendário ao qual este turno pertence */
    public function calendario(): BelongsTo
    {
        return $this->belongsTo(Calendario::class);
    }

    /** Dias da semana em que este turno está disponível */
    public function diasUteis(): HasMany
    {
        return $this->hasMany(DiaUtil::class)->orderBy('dia_semana');
    }

    /** Retorna true se este turno cruza a meia-noite */
    public function ehNoturno(): bool
    {
        return $this->hora_fim < $this->hora_inicio;
    }
}
