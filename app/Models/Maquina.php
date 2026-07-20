<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Maquina extends Model
{
    protected $fillable = [
        'codigo',
        'codigo_recurso',
        'nome',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function calendarioSopro(): HasOne
    {
        return $this->hasOne(CalendarioSopro::class);
    }

    public function frascos(): HasMany
    {
        return $this->hasMany(Frasco::class);
    }

    public function programacoes(): HasMany
    {
        return $this->hasMany(ProgramacaoSopro::class);
    }

    public function programacoesConfirmadas(): HasMany
    {
        return $this->hasMany(ProgramacaoSopro::class)->where('status', 'confirmada');
    }
}
