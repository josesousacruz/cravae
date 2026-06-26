<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('asaas:sincronizar-pagamentos')->everyMinute();

// Copa: atualização contínua dos bolões conforme os jogos vão abrindo na API.
// 1) Preenche os confrontos de mata-mata (2º bolão: espelha a API por rodada;
//    bolão atual: deriva dos resultados de grupo) antes de casar por par de times.
Schedule::command('jogos:resolver-mata-mata')->everyFifteenMinutes()->withoutOverlapping();
// 2) Casa jogos a eventos (calendário publicado aos poucos + mata-mata definido).
Schedule::command('jogos:vincular-eventos')->everyFifteenMinutes()->withoutOverlapping();
// 3) Puxa placares encerrados da TheSportsDB e dispara a pontuação.
Schedule::command('jogos:sincronizar-resultados')->everyMinute()->withoutOverlapping();
