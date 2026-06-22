<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TheSportsDB — integração de resultados da Copa
    |--------------------------------------------------------------------------
    |
    | Chave da API (free = "123"). A liga "FIFA World Cup" é a 4429 e a
    | temporada da Copa de 2026 é "2026".
    |
    */
    'chave_api' => env('THESPORTSDB_KEY', '123'),
    'base_v1' => 'https://www.thesportsdb.com/api/v1/json',
    'base_v2' => 'https://www.thesportsdb.com/api/v2/json',

    'id_liga_copa' => 4429,
    'temporada_copa' => '2026',

    /*
    | Rodadas (intRound) consultadas na API via eventsround — único endpoint sem
    | teto no plano free (eventsseason corta em 15, eventsday em 3/dia).
    | 1, 2, 3 = as três rodadas da fase de grupos (24 jogos cada).
    | Rodadas de mata-mata podem ser acrescentadas aqui quando definidas.
    */
    'rodadas' => [1, 2, 3],

    /*
    |--------------------------------------------------------------------------
    | De-para de seleções (Nível 1)
    |--------------------------------------------------------------------------
    |
    | Mapeia a sigla FIFA (coluna selecoes.sigla) para o idTeam da TheSportsDB.
    | Montado e verificado via searchteams.php em 2026-06-22.
    |
    | OBS: o strTeamShort da API NÃO é confiável como chave (ex.: Coreia do Sul
    | é "SKO" na API e "KOR" aqui), por isso o vínculo é por idTeam fixo.
    |
    | As seleções "A Definir" (repescagens UEFA e intercontinentais — siglas
    | UD4, UA1, UC3, UB2, IC1, IC2) ficam sem vínculo até serem decididas;
    | serão resolvidas no Nível 2 (casamento de jogos).
    |
    */
    'selecoes' => [
        'MEX' => 134497, // Mexico
        'RSA' => 136482, // South Africa
        'KOR' => 134517, // South Korea
        'CAN' => 140073, // Canada
        'QAT' => 136472, // Qatar
        'SUI' => 134506, // Switzerland
        'BRA' => 134496, // Brazil
        'MAR' => 136139, // Morocco
        'HAI' => 140175, // Haiti
        'SCO' => 136450, // Scotland
        'USA' => 134514, // USA
        'PAR' => 136471, // Paraguay
        'AUS' => 134500, // Australia
        'GER' => 133907, // Germany
        'CUW' => 140271, // Curacao
        'CIV' => 134502, // Ivory Coast
        'ECU' => 134507, // Ecuador
        'NED' => 133905, // Netherlands
        'JPN' => 134503, // Japan
        'TUN' => 136142, // Tunisia
        'BEL' => 134515, // Belgium
        'EGY' => 136138, // Egypt
        'IRN' => 134511, // Iran
        'NZL' => 137449, // New Zealand
        'ESP' => 133909, // Spain
        'CPV' => 136477, // Cape Verde
        'KSA' => 136137, // Saudi Arabia
        'URU' => 134504, // Uruguay
        'FRA' => 133913, // France
        'SEN' => 136143, // Senegal
        'NOR' => 136516, // Norway
        'ARG' => 134509, // Argentina
        'ALG' => 134516, // Algeria
        'AUT' => 135986, // Austria
        'JOR' => 140145, // Jordan
        'POR' => 133908, // Portugal
        'UZB' => 140151, // Uzbekistan
        'COL' => 134501, // Colombia
        'ENG' => 133914, // England
        'CRO' => 133912, // Croatia
        'GHA' => 134513, // Ghana
        'PAN' => 136141, // Panama

        // Vencedores das repescagens (mar/2026), antes "A Definir":
        'BIH' => 134510, // Bosnia-Herzegovina (Repescagem UEFA A)
        'SWE' => 133916, // Sweden             (Repescagem UEFA B)
        'TUR' => 135985, // Turkey             (Repescagem UEFA C)
        'CZE' => 133904, // Czech Republic     (Repescagem UEFA D)
        'COD' => 136475, // DR Congo           (Intercontinental 1)
        'IRQ' => 140148, // Iraq               (Intercontinental 2)
    ],
];
