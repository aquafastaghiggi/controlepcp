<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Calendário de trabalho vinculado a uma linha de produção.
 * Define os turnos disponíveis (intervalos), os dias válidos por turno
 * e as datas bloqueadas (feriados/paradas programadas).
 */
class Calendario extends Model
{
    protected $table = 'calendarios';

    protected $fillable = [
        'linha_id',
        'nome',
    ];

    /** Linha à qual este calendário pertence */
    public function linha(): BelongsTo
    {
        return $this->belongsTo(Linha::class);
    }

    /** Todos os turnos configurados, na ordem de exibição */
    public function intervalos(): HasMany
    {
        return $this->hasMany(Intervalo::class)->orderBy('ordem');
    }

    /** Apenas turnos ativos — usados pelo SequenciadorService no cálculo */
    public function intervalosAtivos(): HasMany
    {
        return $this->hasMany(Intervalo::class)
            ->where('ativo', true)
            ->orderBy('ordem');
    }

    /** Datas bloqueadas para produção */
    public function feriados(): HasMany
    {
        return $this->hasMany(Feriado::class)->orderBy('data');
    }
}
