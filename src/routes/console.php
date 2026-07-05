<?php

use App\Jobs\EnviarAvisosAtraso;
use App\Jobs\EnviarLembretes;
use App\Jobs\GerarParcelasRecorrentes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('score:atualizar-atrasados')->dailyAt('01:00');
Schedule::job(new EnviarLembretes)->dailyAt('08:00');
Schedule::job(new EnviarAvisosAtraso)->dailyAt('09:00');

// No dia 25 de cada mês: gera parcela do mês seguinte para cobranças recorrentes
Schedule::job(new GerarParcelasRecorrentes)->monthlyOn(25, '07:00');

// Domingo às 03:00: remove logs de mensagens com mais de 90 dias
Schedule::command('mensagens:limpar-log')->weekly()->sundays()->at('03:00');

// Backup diário do banco às 02:00
Schedule::command('backup:run --only-db')->dailyAt('02:00');

// Limpeza de backups antigos às 02:30
Schedule::command('backup:clean')->dailyAt('02:30');
