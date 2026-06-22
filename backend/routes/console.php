<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('asaas:sincronizar-pagamentos')->everyMinute();

// Copa: puxa placares encerrados da TheSportsDB e dispara a pontuação.
Schedule::command('jogos:sincronizar-resultados')->everyMinute()->withoutOverlapping();
// Casa jogos novos a eventos (calendário publicado aos poucos + mata-mata definido).
Schedule::command('jogos:vincular-eventos')->hourly()->withoutOverlapping();
