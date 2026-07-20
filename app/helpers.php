<?php

if (!function_exists('minutos_para_hhmm')) {
    /**
     * Converte minutos inteiros para formato hh:mm.
     * Ex: 90 → "01:30" | 5 → "00:05" | 125 → "02:05"
     */
    function minutos_para_hhmm(int|float|null $minutos): string
    {
        if ($minutos === null) return '—';
        $minutos = max(0, (int) $minutos);
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;
        return str_pad((string) $h, 2, '0', STR_PAD_LEFT)
             . ':'
             . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}
