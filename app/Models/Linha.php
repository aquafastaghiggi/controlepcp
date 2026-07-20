<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Linha de produção física da fábrica.
 * É o ponto central do sistema: tudo gira em torno de uma linha —
 * seu calendário, seus produtos habilitados e suas programações.
 */
class Linha extends Model
{
    protected $table = 'linhas';

    use HasFactory;

    protected $fillable = [
        'codigo',
        'nome',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /** Apenas linhas ativas aparecem para seleção de nova programação */
    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }

    /** Calendário de trabalho desta linha (1:1) */
    public function calendario(): HasOne
    {
        return $this->hasOne(Calendario::class);
    }

    /** Programações de produção realizadas nesta linha */
    public function programacoes(): HasMany
    {
        return $this->hasMany(Programacao::class);
    }
}
