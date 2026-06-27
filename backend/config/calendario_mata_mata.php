<?php

/*
|--------------------------------------------------------------------------
| Calendário oficial do mata-mata — Copa do Mundo 2026 (FIFA)
|--------------------------------------------------------------------------
|
| Fonte única de verdade das datas/horários do mata-mata, usada por:
|   - TorneioMockadoSeeder e BolaoMataMataSeeder (semeadura inicial / fallback)
|   - Console\Commands\CorrigirDatasMataMata (aplica no banco vivo sem tocar pontos)
|
| IMPORTANTE — convenção de horário:
|   data_hora_inicio é WALL-CLOCK de Brasília (UTC-3) armazenado "naive".
|   O frontend exibe verbatim (timeZone UTC sobre o valor serializado com Z),
|   e o agrupamento por dia usa o prefixo YYYY-MM-DD = dia civil em Brasília.
|   Portanto os horários abaixo são em HORÁRIO DE BRASÍLIA.
|
| Datas oficiais (FIFA):
|   Round of 32 ...... 28/06 (1 jogo) -> 03/07 (3/dia) = 16 jogos
|   Oitavas (R16) .... 04/07 -> 07/07 (2/dia)          = 8 jogos
|   Quartas .......... 09/07, 10/07 (1/dia), 11/07 (2) = 4 jogos
|   Semifinais ....... 14/07, 15/07 (1/dia)            = 2 jogos
|   Terceiro lugar ... 18/07                            = 1 jogo
|   Final ............ 19/07                            = 1 jogo
|
| O índice (0-based) de cada lista mapeia para `ordem_na_fase = índice + 1`.
| Estes horários são ÂNCORA/fallback: jogos.vincular-eventos sobrescreve com o
| horário real da TheSportsDB assim que cada confronto é definido.
*/

return [
    'jogos' => [
        'round_of_32' => [
            '2026-06-28 16:00',
            '2026-06-29 13:00', '2026-06-29 16:00', '2026-06-29 19:00',
            '2026-06-30 13:00', '2026-06-30 16:00', '2026-06-30 19:00',
            '2026-07-01 13:00', '2026-07-01 16:00', '2026-07-01 19:00',
            '2026-07-02 13:00', '2026-07-02 16:00', '2026-07-02 19:00',
            '2026-07-03 13:00', '2026-07-03 16:00', '2026-07-03 19:00',
        ],
        'oitavas_de_final' => [
            '2026-07-04 14:00', '2026-07-04 18:00',
            '2026-07-05 14:00', '2026-07-05 18:00',
            '2026-07-06 14:00', '2026-07-06 18:00',
            '2026-07-07 14:00', '2026-07-07 18:00',
        ],
        'quartas_de_final' => [
            '2026-07-09 16:00',
            '2026-07-10 16:00',
            '2026-07-11 13:00', '2026-07-11 17:00',
        ],
        'semifinais' => [
            '2026-07-14 16:00',
            '2026-07-15 16:00',
        ],
        'terceiro_lugar' => [
            '2026-07-18 15:00',
        ],
        'final' => [
            '2026-07-19 16:00',
        ],
    ],

    // data_fechamento de cada Fase (cosmético no gate atual, mas mantido coerente
    // com o 1º jogo da fase: meio-dia do dia de abertura da fase).
    'fechamento_fase' => [
        'round_of_32' => '2026-06-28 12:00:00',
        'oitavas_de_final' => '2026-07-04 12:00:00',
        'quartas_de_final' => '2026-07-09 12:00:00',
        'semifinais' => '2026-07-14 12:00:00',
        'terceiro_lugar' => '2026-07-18 12:00:00',
        'final' => '2026-07-19 12:00:00',
    ],
];
