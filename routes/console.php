<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Agendamentos automáticos do ControlePCP v2
 *
 * Em produção, adicionar ao crontab do www-data:
 * * * * * cd /var/www/aquafast/controlepcp_v2 && php artisan schedule:run >> /dev/null 2>&1
 *
 * Em desenvolvimento (loop interno, não usar em produção):
 * php artisan schedule:work
 */

// CIGAM — Sincronizar produtos e matriz de setup
// Roda todo dia às 05:00 (antes do expediente)
Schedule::command('cigam:importar-produtos')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// CODI — Performance + Eventos do dia (tudo junto a cada 10 minutos)
Schedule::command('codi:sincronizar --tipo=todos')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// CODI — Apenas eventos a cada 1 minuto (atualização rápida para o TV Dashboard)
Schedule::command('codi:sincronizar --tipo=eventos')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// PCP — Verificar divergências entre OPs programadas e OPs rodando no CODI
Schedule::command('pcp:verificar-divergencias')
    ->cron('*/40 * * * *')
    ->withoutOverlapping()
    ->runInBackground();

// Sopro — Verificar excesso de produção no dia produtivo 06:30–06:30
Schedule::command('sopro:verificar-excesso-producao')
    ->dailyAt('06:35')
    ->withoutOverlapping()
    ->runInBackground();

// PCP — Gravar previsto do dia (envase) — imutável após gravado
Schedule::command('pcp:gravar-previsto-hoje')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Backup diário do banco de dados (retenção de 14 dias, salvo em storage/app/backups)
Schedule::command('backup:banco')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground();
