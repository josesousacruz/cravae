<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compra de cupons aberta
    |--------------------------------------------------------------------------
    |
    | Controla se novos cupons podem ser comprados. A compra fica encerrada
    | durante o campeonato (o dinheiro e recebido por fora do sistema), entao
    | o padrao e "false". Defina CHECKOUT_COMPRAS_ABERTAS=true para reabrir.
    |
    */

    'compras_abertas' => env('CHECKOUT_COMPRAS_ABERTAS', false),

];
