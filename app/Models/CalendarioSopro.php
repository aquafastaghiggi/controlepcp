<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioSopro extends Model
{
    protected $table = 'calendarios_sopro';

    protected $fillable = ['maquina_id', 'nome'];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function intervalos(): HasMany
    {
        return $this->hasMany(IntervaloSopro::class, 'calendario_sopro_id');
    }

    public function intervalosAtivos(): HasMany
    {
        return $this->hasMany(IntervaloSopro::class, 'calendario_sopro_id')->where('ativo', true)->orderBy('ordem');
    }

    public function feriados(): HasMany
    {
        return $this->hasMany(FeriadoSopro::class, 'calendario_sopro_id');
    }
}
